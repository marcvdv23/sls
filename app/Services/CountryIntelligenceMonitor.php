<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryTopic;
use App\Models\CountryUpdate;
use App\Models\IntelligenceKeyword;
use App\Models\IntelligenceSource;
use App\Support\TitleLanguage;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CountryIntelligenceMonitor
{
    /** @var array<string, \Illuminate\Support\Collection<int, array<string, mixed>>> */
    private array $officialTenderApiCache = [];

    /** @var array<string, \Illuminate\Support\Collection<int, array<string, mixed>>> */
    private array $developmentPartnerTenderCache = [];

    public function __construct(private TitleTranslationService $translator)
    {
    }

    public function run(array $countryKeys = [], int $maxResults = 10, bool $dryRun = false, ?int $cycleSize = null, ?int $cycleSlot = null, ?int $slotsPerDay = null, ?Carbon $cycleDate = null, string $focus = 'social_security', ?string $region = null): array
    {
        $countries = $this->plan($countryKeys, $cycleSize, $cycleSlot, $slotsPerDay, $cycleDate, $region);

        return $countries
            ->mapWithKeys(fn (array $countryConfig, string $isoCode) => [
                $isoCode => $this->monitorCountry($countryConfig, $maxResults, $dryRun, $focus),
            ])
            ->all();
    }

    public function plan(array $countryKeys = [], ?int $cycleSize = null, ?int $cycleSlot = null, ?int $slotsPerDay = null, ?Carbon $cycleDate = null, ?string $region = null): Collection
    {
        $countries = $this->selectedCountries($countryKeys, $region);

        if ($cycleSize !== null && $cycleSize > 0) {
            $countries = $this->cycleCountries($countries, $cycleSize, $cycleDate ?? now(), $cycleSlot, $slotsPerDay);
        }

        return $countries;
    }

    public function monitorCountry(array $countryConfig, int $maxResults, bool $dryRun = false, string $focus = 'social_security'): array
    {
        $startedAt = now();
        $focus = $this->focusKey($focus);
        $country = $dryRun ? null : $this->ensureCountry($countryConfig);
        $topic = $dryRun || ! $country ? null : $this->ensureTopic($country);
        $candidateItems = collect();
        $activeCountrySources = collect($countryConfig['sources'] ?? [])->filter(fn (array $source) => $this->sourceMatchesFocus($source, $focus));
        $sourcesChecked = $activeCountrySources->pluck('domain')->push('search.worldbank.org');

        if (in_array($focus, ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'], true)) {
            $sourcesChecked = $sourcesChecked->merge($this->developmentPartnerTenderSources()->pluck('domain'));
        }

        $sourcesChecked = $sourcesChecked->filter()->unique()->values()->all();

        $queryLimit = $this->crawlerSettingInteger('max_queries_per_country', config('country_intelligence.max_queries_per_country', 8));

        foreach (array_slice($this->queriesFor($countryConfig, $focus), 0, $queryLimit) as $query) {
            $candidateItems = $candidateItems->merge($this->searchGdelt($query, max(6, min($maxResults, 12))));
        }

        $candidateItems = $candidateItems->merge($this->searchWorldBankProcurement($countryConfig, $focus, max(5, $maxResults)));

        if (in_array($focus, ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'], true)) {
            $candidateItems = $candidateItems->merge($this->searchOfficialTenderApis($countryConfig, $focus, max(5, $maxResults)));
            $candidateItems = $candidateItems->merge($this->searchDevelopmentPartnerTenderSources($countryConfig, $focus, max(5, $maxResults)));
        }

        foreach ($activeCountrySources as $source) {
            if (($source['type'] ?? null) === 'wordpress') {
                $candidateItems = $candidateItems->merge($this->searchWordPress($source, $countryConfig, max(3, $maxResults)));
            }

            $candidateItems = $candidateItems->merge($this->searchRssFeeds($source, max(5, $maxResults)));
        }

        $candidateItems = $candidateItems->merge($this->knownItems($countryConfig, $focus));

        $items = $candidateItems
            ->map(fn (array $item) => $this->normalizeItem($item, $countryConfig, $focus))
            ->filter(fn (array $item) => $item['source_url'] && $this->itemMatchesCountry($item, $countryConfig) && $this->isRelevant($item, $focus))
            ->unique('source_url')
            ->sortByDesc('relevance_score')
            ->take($maxResults)
            ->values();

        if (! $dryRun && $country) {
            $storedUpdates = $items->map(fn (array $item) => $this->storeUpdate($country, $topic, $item));
            $this->storeMonitorRun(
                $country,
                $focus,
                $sourcesChecked,
                $storedUpdates->filter(fn (CountryUpdate $update) => $update->wasRecentlyCreated)->count(),
                $startedAt
            );
        }

        return [
            'country' => $countryConfig['name'],
            'focus' => $focus,
            'sources_checked' => $sourcesChecked,
            'items_found' => $items->count(),
            'items' => $items->all(),
        ];
    }

    private function selectedCountries(array $countryKeys, ?string $region = null): Collection
    {
        $configured = $this->configuredCountries();

        if ($region !== null && trim($region) !== '') {
            $wantedRegion = Str::lower(trim($region));
            $wantedRegions = match ($wantedRegion) {
                'africa_asia', 'asia_africa', 'africa+asia', 'africa,asia' => ['africa', 'asia'],
                'north_america', 'north-america', 'north america' => ['north america'],
                'europe' => ['europe'],
                'africa_asia_caribbean_latin_america_north_america',
                'africa_asia_caribbean_latin_america_north_america_europe',
                'all_core_regions' => ['africa', 'asia', 'caribbean', 'latin america', 'north america', 'europe'],
                default => collect(explode(',', $wantedRegion))->map(fn (string $region) => trim($region))->filter()->all(),
            };

            $configured = $configured->filter(fn (array $countryConfig) => in_array(Str::lower((string) ($countryConfig['region'] ?? '')), $wantedRegions, true));
        }

        if ($countryKeys === []) {
            return $configured;
        }

        $wanted = collect($countryKeys)
            ->map(fn (string $key) => Str::of($key)->trim()->lower()->toString())
            ->filter()
            ->values();

        return $configured->filter(function (array $countryConfig, string $isoCode) use ($wanted) {
            $aliases = collect([
                strtolower($isoCode),
                strtolower($countryConfig['iso_code'] ?? ''),
                strtolower($countryConfig['name'] ?? ''),
            ])->merge(collect($countryConfig['search_names'] ?? [])->map(fn ($name) => strtolower((string) $name)));

            return $wanted->contains(fn (string $key) => $aliases->contains($key));
        });
    }

    private function configuredCountries(): Collection
    {
        $baseCountries = collect(config('country_intelligence.monitored_countries', []))
            ->map(function (array $countryConfig, string $isoCode) {
                $countryConfig['iso_code'] = $countryConfig['iso_code'] ?? $isoCode;
                $countryConfig['search_names'] = $countryConfig['search_names'] ?? [$countryConfig['name']];
                $countryConfig['priority_queries'] = $countryConfig['priority_queries'] ?? [
                    '"' . $countryConfig['name'] . '" "social security"',
                    '"' . $countryConfig['name'] . '" pension',
                    '"' . $countryConfig['name'] . '" tender',
                    '"' . $countryConfig['name'] . '" procurement',
                    '"' . $countryConfig['name'] . '" "ministry of labor"',
                    '"' . $countryConfig['name'] . '" "ministry of labour"',
                ];
                $countryConfig['sources'] = $countryConfig['sources'] ?? [];

                return $countryConfig;
            });

        $overrides = collect(config('country_intelligence.countries', []));

        return $baseCountries
            ->map(fn (array $countryConfig, string $isoCode) => array_replace_recursive($countryConfig, $overrides->get($isoCode, [])))
            ->merge($overrides->except($baseCountries->keys()->all()))
            ->map(function (array $countryConfig, string $isoCode) use ($baseCountries) {
                if (! isset($countryConfig['sources']) || ! is_array($countryConfig['sources'])) {
                    $countryConfig['sources'] = [];
                }

                $countryConfig['iso_code'] = $countryConfig['iso_code'] ?? $isoCode;
                $countryConfig['search_names'] = array_values(array_unique($countryConfig['search_names'] ?? [$countryConfig['name']]));
                $countryConfig['priority_queries'] = array_values(array_unique($countryConfig['priority_queries'] ?? []));
                $countryConfig['sources'] = collect($countryConfig['sources'])
                    ->merge($this->databaseCountrySources($countryConfig))
                    ->unique(fn (array $source) => Str::lower((string) ($source['domain'] ?? '')) . '|' . Str::lower((string) ($source['url'] ?? '')))
                    ->values()
                    ->all();

                return $countryConfig;
            });
    }

    private function cycleCountries(Collection $countries, int $cycleSize, Carbon $cycleDate, ?int $cycleSlot = null, ?int $slotsPerDay = null): Collection
    {
        $countries = $countries->sortKeys();
        $count = $countries->count();

        if ($count === 0 || $cycleSize >= $count) {
            return $countries;
        }

        $slotsPerDay = max(1, $slotsPerDay ?? 1);
        $cycleSlot = max(0, $cycleSlot ?? 0);
        $totalBatches = (int) ceil($count / $cycleSize);
        $batchIndex = (((max(0, $cycleDate->dayOfYear - 1) * $slotsPerDay) + $cycleSlot) % $totalBatches);
        $offset = $batchIndex * $cycleSize;

        return $countries->slice($offset, $cycleSize);
    }

    private function ensureCountry(array $countryConfig): Country
    {
        return Country::query()->updateOrCreate(
            ['iso_code' => $countryConfig['iso_code']],
            [
                'name' => $countryConfig['name'],
                'region' => $countryConfig['region'] ?? null,
                'default_language_code' => $countryConfig['default_language_code'] ?? 'en',
                'social_security_administration_name' => $countryConfig['social_security_administration_name'] ?? null,
                'profile_status' => 'draft',
            ],
        );
    }

    private function ensureTopic(Country $country): CountryTopic
    {
        return CountryTopic::query()->updateOrCreate(
            ['country_id' => $country->id, 'topic' => 'social security and pension intelligence'],
            ['tracking_status' => 'active'],
        );
    }

    private function queriesFor(array $countryConfig, string $focus): array
    {
        $names = collect($countryConfig['search_names'] ?? [$countryConfig['name']])
            ->merge(config('country_intelligence.localized_country_names.' . ($countryConfig['iso_code'] ?? ''), []))
            ->unique()
            ->take(8);
        $focusTerms = collect(config("country_intelligence.focuses.$focus.terms", config('country_intelligence.topics', [])))
            ->merge($this->databaseKeywordTerms($focus, ['en']))
            ->merge($this->localizedFocusTerms($countryConfig, $focus))
            ->unique()
            ->values();
        $queryTerms = $focusTerms->take(in_array($focus, ['hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'], true) ? 10 : 8)->all();

        $general = $names
            ->flatMap(fn (string $name) => collect($queryTerms)->map(fn (string $term) => '"' . $name . '" "' . $term . '"'))
            ->all();

        $sourceSpecific = collect($countryConfig['sources'] ?? [])
            ->filter(fn (array $source) => $this->sourceMatchesFocus($source, $focus))
            ->take(5)
            ->pluck('domain')
            ->filter()
            ->flatMap(fn (string $domain) => collect($queryTerms)->take(6)->map(fn (string $term) => 'domain:' . $domain . ' "' . $term . '"'))
            ->all();

        $priorityQueries = collect($countryConfig['priority_queries'] ?? []);

        if ($focus === 'hrms_tenders') {
            $priorityQueries = $names->flatMap(fn (string $name) => [
                '"' . $name . '" HRMS tender',
                '"' . $name . '" payroll RFP',
                '"' . $name . '" HCM procurement',
                '"' . $name . '" "human resources management system"',
                '"' . $name . '" "human resource information system"',
            ])->merge($names->flatMap(fn (string $name) => $this->hrmsTargetIndustryTerms()
                ->take(10)
                ->flatMap(fn (string $industry) => [
                    '"' . $name . '" "' . $industry . '" HRMS tender',
                    '"' . $name . '" "' . $industry . '" payroll procurement',
                    '"' . $name . '" "' . $industry . '" "benefits administration"',
                    '"' . $name . '" "' . $industry . '" "talent management"',
                    '"' . $name . '" "' . $industry . '" "performance management"',
                ])))->merge($priorityQueries);
        }

        if ($focus === 'sector_tenders') {
            $priorityQueries = $names->flatMap(fn (string $name) => [
                '"' . $name . '" telecom tender',
                '"' . $name . '" oil gas tender',
                '"' . $name . '" postal services tender',
                '"' . $name . '" civil service procurement',
                '"' . $name . '" public administration RFP',
                '"' . $name . '" airline aviation tender',
                '"' . $name . '" mining tender',
                '"' . $name . '" banking finance tender',
                '"' . $name . '" central bank procurement',
            ])->merge($priorityQueries);
        }

        if ($focus === 'social_security') {
            $iloProjectTerms = collect(config('country_intelligence.ilo_social_protection_project_terms', []))
                ->filter()
                ->take(3);

            $priorityQueries = $names
                ->take(2)
                ->flatMap(fn (string $name) => $iloProjectTerms->map(
                    fn (string $term) => 'domain:social-protection.org "Contribution.action" "' . $name . '" "' . $term . '"'
                ))
                ->merge($priorityQueries);
        }

        return array_values(array_unique(array_merge($priorityQueries->all(), $general, $sourceSpecific)));
    }

    private function localizedFocusTerms(array $countryConfig, string $focus): Collection
    {
        $languageCodes = $this->searchLanguageCodes($countryConfig);

        return $languageCodes
            ->flatMap(fn (string $languageCode) => config("country_intelligence.localized_focus_terms.$focus.$languageCode", []))
            ->merge($this->databaseKeywordTerms($focus, $languageCodes->all()))
            ->filter()
            ->values();
    }

    private function databaseKeywordTerms(string $focus, array $languageCodes = ['en']): Collection
    {
        if (! Schema::hasTable('intelligence_keywords')) {
            return collect();
        }

        $languageCodes = collect($languageCodes)
            ->push('en')
            ->map(fn ($code) => Str::lower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return IntelligenceKeyword::query()
            ->where('is_enabled', true)
            ->where('focus', $focus)
            ->whereIn('language_code', $languageCodes)
            ->pluck('term')
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            ->values();
    }

    private function databaseCountrySources(array $countryConfig): Collection
    {
        if (! Schema::hasTable('intelligence_sources')) {
            return collect();
        }

        $iso = strtoupper((string) ($countryConfig['iso_code'] ?? ''));
        $region = Str::lower((string) ($countryConfig['region'] ?? ''));

        return IntelligenceSource::query()
            ->where('is_enabled', true)
            ->where(function ($query) use ($iso, $region) {
                $query->where('country_iso', $iso);

                if ($region !== '') {
                    $query->orWhereRaw('LOWER(region) = ?', [$region]);
                }
            })
            ->get()
            ->map(fn (IntelligenceSource $source) => $this->intelligenceSourceToCrawlerSource($source))
            ->filter(fn (array $source) => filled($source['domain'] ?? null) || filled($source['url'] ?? null))
            ->values();
    }

    private function databaseGlobalTenderSources(): Collection
    {
        if (! Schema::hasTable('intelligence_sources')) {
            return collect();
        }

        return IntelligenceSource::query()
            ->where('is_enabled', true)
            ->whereNull('country_iso')
            ->whereIn('source_class', ['donor_tender_portal', 'central_tender_portal', 'news_aggregator'])
            ->get()
            ->map(fn (IntelligenceSource $source) => $this->intelligenceSourceToCrawlerSource($source))
            ->filter(fn (array $source) => filled($source['domain'] ?? null) || filled($source['url'] ?? null))
            ->values();
    }

    private function intelligenceSourceToCrawlerSource(IntelligenceSource $source): array
    {
        return [
            'name' => $source->name,
            'domain' => $source->domain,
            'url' => $source->url,
            'source_class' => $source->source_class,
            'focus' => $source->focus,
            'access_method' => $source->access_method,
            'connector' => $source->connector,
            'procurement_portal_type' => $source->procurement_portal_type,
            'registration_notes' => $source->registration_notes,
        ];
    }

    private function crawlerSetting(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('crawler_settings')) {
                return $default;
            }

            $value = \Illuminate\Support\Facades\DB::table('crawler_settings')
                ->where('setting_key', $key)
                ->value('setting_value');

            return filled($value) ? $value : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    private function crawlerSettingString(string $key, mixed $default = ''): string
    {
        return trim((string) $this->crawlerSetting($key, $default));
    }

    private function crawlerSettingInteger(string $key, mixed $default = 0): int
    {
        return (int) $this->crawlerSetting($key, $default);
    }

    private function crawlerSettingBoolean(string $key, mixed $default = false): bool
    {
        return filter_var($this->crawlerSetting($key, $default), FILTER_VALIDATE_BOOL);
    }

    private function sourceMatchesFocus(array $source, string $focus): bool
    {
        $sourceFocus = (string) ($source['focus'] ?? 'both');

        return $sourceFocus === 'both'
            || $sourceFocus === $focus
            || ($sourceFocus === 'news' && $focus === 'social_security')
            || ($sourceFocus === 'tenders' && in_array($focus, ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'], true));
    }

    private function searchLanguageCodes(array $countryConfig): Collection
    {
        $isoCode = strtoupper((string) ($countryConfig['iso_code'] ?? ''));
        $codes = collect([$countryConfig['default_language_code'] ?? null]);

        $countryLanguageMap = [
            'DZ' => ['ar', 'fr'], 'BH' => ['ar'], 'EG' => ['ar'], 'IQ' => ['ar'], 'JO' => ['ar'], 'KW' => ['ar'],
            'LB' => ['ar', 'fr'], 'LY' => ['ar'], 'MA' => ['ar', 'fr'], 'MR' => ['ar', 'fr'], 'OM' => ['ar'],
            'PS' => ['ar'], 'QA' => ['ar'], 'SA' => ['ar'], 'SD' => ['ar'], 'SY' => ['ar'], 'TN' => ['ar', 'fr'],
            'AE' => ['ar'], 'YE' => ['ar'], 'CN' => ['zh'], 'JP' => ['ja'], 'KR' => ['ko'], 'TH' => ['th'],
            'VN' => ['vi'], 'ID' => ['id'], 'MY' => ['ms'], 'BN' => ['ms'], 'TR' => ['tr'], 'IR' => ['fa'],
            'AF' => ['fa'], 'PK' => ['ur'], 'BD' => ['bn'], 'IN' => ['hi'], 'LK' => ['en'], 'NP' => ['en'],
        ];

        return $codes
            ->merge($countryLanguageMap[$isoCode] ?? [])
            ->map(fn ($code) => Str::lower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();
    }

    private function searchGdelt(string $query, int $maxRecords): Collection
    {
        $request = Http::timeout(5)
            ->connectTimeout(3)
            ->acceptJson();

        if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->get($this->crawlerSettingString('gdelt_endpoint', config('country_intelligence.gdelt_endpoint')), [
                'query' => $query,
                'mode' => 'ArtList',
                'format' => 'json',
                'sort' => 'DateDesc',
                'maxrecords' => $maxRecords,
                'timespan' => $this->crawlerSettingString('gdelt_timespan', config('country_intelligence.gdelt_timespan', '30d')),
            ]);
        } catch (Throwable) {
            return collect();
        }

        if (! $response->ok()) {
            return collect();
        }

        return collect($response->json('articles', []));
    }

    private function searchWordPress(array $source, array $countryConfig, int $maxRecords): Collection
    {
        $baseUrl = rtrim((string) ($source['url'] ?? ''), '/');

        if ($baseUrl === '') {
            return collect();
        }

        $terms = collect([
            'pension',
            'social security',
            'contribution',
            'benefit',
            'cheques',
            'tender',
            'procurement',
        ])->merge($countryConfig['search_names'] ?? [])->unique()->take(10);

        return $terms->flatMap(function (string $term) use ($baseUrl, $source, $maxRecords) {
            $request = Http::timeout(8)->connectTimeout(4)->acceptJson();

            if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
                $request = $request->withoutVerifying();
            }

            try {
                $response = $request->get($baseUrl . '/wp-json/wp/v2/posts', [
                    'search' => $term,
                    'per_page' => min(5, $maxRecords),
                    'orderby' => 'date',
                    'order' => 'desc',
                ]);
            } catch (Throwable) {
                return [];
            }

            if (! $response->ok()) {
                return [];
            }

            return collect($response->json())->map(function (array $post) use ($source) {
                return [
                    'title' => $this->plainText((string) Arr::get($post, 'title.rendered', '')),
                    'url' => (string) Arr::get($post, 'link', ''),
                    'domain' => (string) ($source['domain'] ?? parse_url((string) Arr::get($post, 'link', ''), PHP_URL_HOST)),
                    'sourcecountry' => (string) ($source['name'] ?? ''),
                    'seendate' => (string) Arr::get($post, 'date_gmt', Arr::get($post, 'date', '')),
                    'excerpt' => $this->plainText((string) Arr::get($post, 'excerpt.rendered', '')),
                    'content' => $this->plainText((string) Arr::get($post, 'content.rendered', '')),
                ];
            });
        })->values();
    }

    private function searchRssFeeds(array $source, int $maxRecords): Collection
    {
        $baseUrl = rtrim((string) ($source['url'] ?? ''), '/');

        if ($baseUrl === '') {
            return collect();
        }

        $hostUrl = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);
        $feedUrls = collect([
            $baseUrl . '/feed/',
            $baseUrl . '/feed',
            $baseUrl . '/rss',
            $baseUrl . '/rss.xml',
            $hostUrl . '/feed/',
            $hostUrl . '/rss',
            $hostUrl . '/rss.xml',
        ])->filter()->unique()->values();

        foreach ($feedUrls as $feedUrl) {
            $request = Http::timeout(8)->connectTimeout(4)->accept('application/rss+xml, application/xml, text/xml');

            if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
                $request = $request->withoutVerifying();
            }

            try {
                $response = $request->get($feedUrl);
            } catch (Throwable) {
                continue;
            }

            if (! $response->ok() || trim($response->body()) === '') {
                continue;
            }

            $items = $this->parseFeedItems($response->body(), $source, $maxRecords);

            if ($items->isNotEmpty()) {
                return $items;
            }
        }

        return collect();
    }

    private function parseFeedItems(string $xml, array $source, int $maxRecords): Collection
    {
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $feed) {
            return collect();
        }

        if (isset($feed->channel->item)) {
            return collect($feed->channel->item)
                ->take($maxRecords)
                ->map(fn ($item) => [
                    'title' => $this->plainText((string) $item->title),
                    'url' => trim((string) $item->link),
                    'domain' => (string) ($source['domain'] ?? parse_url(trim((string) $item->link), PHP_URL_HOST)),
                    'sourcecountry' => (string) ($source['name'] ?? ''),
                    'seendate' => (string) ($item->pubDate ?? $item->children('dc', true)->date ?? ''),
                    'excerpt' => $this->plainText((string) ($item->description ?? '')),
                    'content' => $this->plainText((string) ($item->children('content', true)->encoded ?? '')),
                ])
                ->filter(fn (array $item) => $item['title'] !== '' && $item['url'] !== '')
                ->values();
        }

        if (isset($feed->entry)) {
            return collect($feed->entry)
                ->take($maxRecords)
                ->map(function ($entry) use ($source) {
                    $link = '';

                    foreach ($entry->link ?? [] as $candidate) {
                        $attributes = $candidate->attributes();
                        $href = (string) ($attributes['href'] ?? '');

                        if ($href !== '') {
                            $link = $href;
                            break;
                        }
                    }

                    return [
                        'title' => $this->plainText((string) $entry->title),
                        'url' => $link,
                        'domain' => (string) ($source['domain'] ?? parse_url($link, PHP_URL_HOST)),
                        'sourcecountry' => (string) ($source['name'] ?? ''),
                        'seendate' => (string) ($entry->updated ?? $entry->published ?? ''),
                        'excerpt' => $this->plainText((string) ($entry->summary ?? '')),
                        'content' => $this->plainText((string) ($entry->content ?? '')),
                    ];
                })
                ->filter(fn (array $item) => $item['title'] !== '' && $item['url'] !== '')
                ->values();
        }

        return collect();
    }

    private function searchWorldBankProcurement(array $countryConfig, string $focus, int $maxRecords): Collection
    {
        $endpoint = $this->crawlerSettingString('world_bank_procurement_endpoint', config('country_intelligence.world_bank_procurement_endpoint', ''));

        if ($endpoint === '') {
            return collect();
        }

        $searchTerms = match ($focus) {
            'hrms_tenders' => collect(['hrms', 'payroll', 'human resource', 'human resources', 'hcm', 'hrmis', 'hrims', 'benefits administration', 'talent management', 'performance management'])
                ->merge($this->hrmsTargetIndustryTerms()->map(fn (string $industry) => $industry . ' payroll'))
                ->all(),
            'erms_tenders' => ['enterprise risk management', 'risk management software', 'risk management system', 'grc software', 'governance risk compliance', 'internal audit software', 'audit management system', 'internal control system', 'compliance management software', 'operational risk management', 'risk register', 'risk assessment system', 'risk information system'],
            'ebpc_tenders' => ['ifmis', 'fmis', 'pfmis', 'integrated financial management information system', 'financial management information system', 'public financial management information system', 'public financial management system', 'budgeting software', 'budget planning software', 'budget control software', 'budget formulation', 'budget management system', 'budget execution system', 'budget preparation system', 'financial planning software', 'performance budgeting'],
            'sector_tenders' => ['telecom', 'telecommunications', 'oil gas', 'postal services', 'civil service', 'public administration', 'aviation', 'mining', 'banking', 'finance'],
            default => ['social security', 'pension', 'provident fund', 'ministry of labor', 'ministry of labour'],
        };

        $countryNames = collect($countryConfig['search_names'] ?? [$countryConfig['name']])
            ->prepend((string) $countryConfig['name'])
            ->unique()
            ->take(in_array($focus, ['hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'], true) ? 3 : 2)
            ->values();

        $termLimit = match ($focus) {
            'sector_tenders' => 10,
            'hrms_tenders', 'erms_tenders', 'ebpc_tenders' => 18,
            default => 3,
        };

        return $countryNames
            ->flatMap(fn (string $countryName) => collect($searchTerms)
                ->take($termLimit)
                ->flatMap(fn (string $term) => $this->queryWorldBankProcurement($endpoint, $countryConfig, $countryName, $term, $focus, $maxRecords)))
            ->unique('url')
            ->values();
    }

    private function searchDevelopmentPartnerTenderSources(array $countryConfig, string $focus, int $maxRecords): Collection
    {
        $cacheKey = $focus . '|' . $maxRecords;

        if (isset($this->developmentPartnerTenderCache[$cacheKey])) {
            return $this->developmentPartnerTenderCache[$cacheKey];
        }

        $sources = $this->developmentPartnerTenderSources();

        if ($sources->isEmpty()) {
            return collect();
        }

        $queryLimit = $this->crawlerSettingInteger('development_partner_max_queries_per_country', config('country_intelligence.development_partner_max_queries_per_country', 18));
        $industryQuery = '("higher education" OR university OR college OR telecom OR telecommunications OR "oil and gas" OR petroleum OR government OR "civil service" OR "public service" OR "public administration" OR postal OR healthcare OR hospital OR "ministry of health")';
        $termQuery = match ($focus) {
            'sector_tenders' => '(telecom OR telecommunications OR "oil and gas" OR postal OR "civil service" OR aviation OR mining OR banking OR finance) (tender OR procurement OR rfp OR bid)',
            'erms_tenders' => '("enterprise risk management" OR "risk management software" OR "risk management system" OR "GRC software" OR "risk register" OR "internal audit software" OR "audit management system" OR "internal control system" OR "compliance management software" OR "operational risk management") (tender OR procurement OR rfp OR bid)',
            'ebpc_tenders' => '(IFMIS OR FMIS OR PFMIS OR "integrated financial management information system" OR "financial management information system" OR "public financial management information system" OR "public financial management system" OR "budgeting software" OR "budget planning software" OR "budget control software" OR "budget formulation" OR "budget execution system" OR "budget preparation system" OR "performance budgeting" OR "financial planning software") (tender OR procurement OR rfp OR bid)',
            'social_security' => '("social security" OR "social insurance" OR pension OR pensions OR "pension administration" OR "provident fund" OR "social protection" OR "national insurance" OR "benefits administration") (tender OR procurement OR rfp OR bid OR "expression of interest")',
            default => '(hrms OR hris OR hcm OR payroll OR "human resources management system" OR "human resource information system" OR "benefits administration" OR "talent management" OR "performance management" OR "workforce management") ' . $industryQuery . ' (tender OR procurement OR rfp OR bid)',
        };

        $queries = $sources
            ->reject(fn (array $source) => ($source['access_method'] ?? null) === 'official_api')
            ->map(fn (array $source) => [
                'query' => 'domain:' . $source['domain'] . ' ' . $termQuery,
                'source' => $source,
            ])
            ->take($queryLimit);

        $items = $queries
            ->flatMap(fn (array $query) => $this->searchGdelt($query['query'], min(6, $maxRecords))
                ->map(function (array $item) use ($query) {
                    $item['sourcecountry'] = $query['source']['name'];
                    $item['domain'] = $item['domain'] ?? $query['source']['domain'];

                    return $item;
                }))
            ->unique(fn (array $item) => (string) ($item['url'] ?? ''))
            ->values();

        $this->developmentPartnerTenderCache[$cacheKey] = $items;

        return $items;
    }

    private function searchOfficialTenderApis(array $countryConfig, string $focus, int $maxRecords): Collection
    {
        $cacheKey = $focus . '|' . $maxRecords;

        if (isset($this->officialTenderApiCache[$cacheKey])) {
            return $this->officialTenderApiCache[$cacheKey];
        }

        $items = $this->developmentPartnerTenderSources()
            ->where('access_method', 'official_api')
            ->flatMap(function (array $source) use ($countryConfig, $focus, $maxRecords) {
                return match ($source['connector'] ?? null) {
                    'ted' => $this->searchTedNotices($source, $countryConfig, $focus, $maxRecords),
                    'usaid_business_forecast' => $this->searchUsaidBusinessForecast($source, $countryConfig, $focus, $maxRecords),
                    'sam_gov_opportunities' => $this->searchSamGovOpportunities($source, $countryConfig, $focus, $maxRecords),
                    default => collect(),
                };
            })
            ->unique(fn (array $item) => (string) ($item['url'] ?? ''))
            ->values();

        $this->officialTenderApiCache[$cacheKey] = $items;

        return $items;
    }

    private function searchTedNotices(array $source, array $countryConfig, string $focus, int $maxRecords): Collection
    {
        $endpoint = $this->crawlerSettingString('ted_search_endpoint', config('country_intelligence.ted_search_endpoint', ''));

        if ($endpoint === '') {
            return collect();
        }

        $queries = $this->topicTenderQueries($focus)->take(3);
        $request = Http::timeout(6)->connectTimeout(3)->acceptJson();

        if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
            $request = $request->withoutVerifying();
        }

        return $queries->flatMap(function (string $query) use ($request, $endpoint, $source, $maxRecords) {
            try {
                $response = $request->post($endpoint, [
                    'query' => $query,
                    'limit' => min(10, $maxRecords),
                    'page' => 1,
                    'fields' => [
                        'publication-number',
                        'notice-title',
                        'publication-date',
                        'deadline-date',
                        'buyer-name',
                        'description',
                        'place-of-performance',
                    ],
                ]);
            } catch (Throwable) {
                return collect();
            }

            if (! $response->successful()) {
                return collect();
            }

            $notices = data_get($response->json(), 'notices', data_get($response->json(), 'results', []));

            return collect(is_array($notices) ? $notices : [])
                ->map(fn (array $notice) => $this->tedNoticeToItem($notice, $source));
        })->values();
    }

    private function searchUsaidBusinessForecast(array $source, array $countryConfig, string $focus, int $maxRecords): Collection
    {
        $endpoint = $this->crawlerSettingString('usaid_business_forecast_endpoint', config('country_intelligence.usaid_business_forecast_endpoint', ''));

        if ($endpoint === '') {
            return collect();
        }

        $request = Http::timeout(6)->connectTimeout(3)->acceptJson();

        if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
            $request = $request->withoutVerifying();
        }

        return $this->topicTenderQueries($focus)
            ->take(3)
            ->flatMap(function (string $query) use ($request, $endpoint, $source, $maxRecords) {
                try {
                    $response = $request->get($endpoint, [
                        '$limit' => min(20, $maxRecords),
                        '$q' => $query,
                    ]);
                } catch (Throwable) {
                    return collect();
                }

                if (! $response->successful()) {
                    return collect();
                }

                $rows = $response->json();

                return collect(is_array($rows) ? $rows : [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => $this->usaidForecastToItem($row, $source));
            })
            ->values();
    }

    private function searchSamGovOpportunities(array $source, array $countryConfig, string $focus, int $maxRecords): Collection
    {
        $endpoint = $this->crawlerSettingString('sam_gov_opportunities_endpoint', config('country_intelligence.sam_gov_opportunities_endpoint', ''));
        $apiKey = $this->crawlerSettingString('sam_gov_api_key', env('SAM_GOV_API_KEY', env('SAM_API_KEY', '')));

        if ($endpoint === '' || $apiKey === '') {
            return collect();
        }

        $request = Http::timeout(8)->connectTimeout(4)->acceptJson();

        if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
            $request = $request->withoutVerifying();
        }

        $postedTo = now()->format('m/d/Y');
        $postedFrom = now()->subDays(120)->format('m/d/Y');

        return $this->topicTenderQueries($focus)
            ->take(8)
            ->flatMap(function (string $query) use ($request, $endpoint, $apiKey, $source, $maxRecords, $postedFrom, $postedTo) {
                try {
                    $response = $request->get($endpoint, [
                        'api_key' => $apiKey,
                        'postedFrom' => $postedFrom,
                        'postedTo' => $postedTo,
                        'title' => $query,
                        'limit' => min(100, max(10, $maxRecords)),
                        'offset' => 0,
                    ]);
                } catch (Throwable) {
                    return collect();
                }

                if (! $response->successful()) {
                    return collect();
                }

                $rows = data_get($response->json(), 'opportunitiesData', []);

                return collect(is_array($rows) ? $rows : [])
                    ->filter(fn ($row) => is_array($row))
                    ->map(fn (array $row) => $this->samGovOpportunityToItem($row, $source));
            })
            ->values();
    }

    private function countryTenderQueries(array $countryConfig, string $focus): Collection
    {
        $countryNames = collect($countryConfig['search_names'] ?? [$countryConfig['name']])
            ->prepend((string) $countryConfig['name'])
            ->unique()
            ->take(2)
            ->values();

        $terms = match ($focus) {
            'hrms_tenders' => ['HRMS', 'HRIS', 'HCM', 'payroll', 'human resources management system', 'benefits administration', 'talent management', 'performance management'],
            'erms_tenders' => ['enterprise risk management', 'risk management software', 'risk management system', 'GRC software', 'governance risk compliance', 'internal audit software', 'audit management system', 'internal control system', 'compliance management software', 'operational risk management'],
            'ebpc_tenders' => ['IFMIS', 'FMIS', 'PFMIS', 'integrated financial management information system', 'financial management information system', 'public financial management information system', 'public financial management system', 'budgeting software', 'budget planning software', 'budget control software', 'budget formulation', 'budget execution system', 'budget preparation system', 'financial planning software', 'performance budgeting'],
            'sector_tenders' => ['telecom', 'oil and gas', 'postal services', 'civil service', 'public administration', 'aviation', 'mining', 'banking', 'finance'],
            default => ['social security', 'social insurance', 'pension administration', 'provident fund', 'social protection', 'national insurance'],
        };

        return $countryNames
            ->flatMap(fn (string $countryName) => collect($terms)
                ->map(fn (string $term) => trim($countryName . ' ' . $term . ' tender procurement rfp')))
            ->unique()
            ->values();
    }

    private function topicTenderQueries(string $focus): Collection
    {
        $terms = match ($focus) {
            'hrms_tenders' => ['HRMS', 'HRIS', 'HCM', 'payroll', 'human resources management system', 'benefits administration', 'talent management', 'performance management'],
            'erms_tenders' => ['enterprise risk management', 'risk management software', 'risk management system', 'GRC software', 'governance risk compliance', 'internal audit software', 'audit management system', 'internal control system', 'compliance management software', 'operational risk management'],
            'ebpc_tenders' => ['IFMIS', 'FMIS', 'PFMIS', 'integrated financial management information system', 'financial management information system', 'public financial management information system', 'public financial management system', 'budgeting software', 'budget planning software', 'budget control software', 'budget formulation', 'budget execution system', 'budget preparation system', 'financial planning software', 'performance budgeting'],
            'sector_tenders' => ['telecom', 'oil and gas', 'postal services', 'civil service', 'public administration', 'aviation', 'mining', 'banking', 'finance'],
            default => ['social security', 'social insurance', 'pension administration', 'provident fund', 'social protection', 'national insurance'],
        };

        return collect($terms)
            ->map(fn (string $term) => trim($term . ' tender procurement rfp expression of interest'))
            ->unique()
            ->values();
    }

    private function tedNoticeToItem(array $notice, array $source): array
    {
        $id = $this->firstStringValue($notice, ['publication-number', 'publicationNumber', 'notice-id', 'id']);
        $title = $this->firstStringValue($notice, ['notice-title', 'noticeTitle', 'title']);
        $description = $this->firstStringValue($notice, ['description', 'summary', 'short-description']);
        $publishedAt = $this->firstStringValue($notice, ['publication-date', 'publicationDate', 'published']);

        return [
            'title' => $title ?: $description ?: 'TED procurement notice',
            'url' => $id !== '' ? 'https://ted.europa.eu/en/notice/-/detail/' . rawurlencode($id) : (string) ($source['url'] ?? 'https://ted.europa.eu/'),
            'domain' => (string) ($source['domain'] ?? 'ted.europa.eu'),
            'sourcecountry' => (string) ($source['name'] ?? 'European Union TED'),
            'seendate' => $publishedAt,
            'excerpt' => trim(implode(' | ', array_filter([
                $description,
                $this->firstStringValue($notice, ['buyer-name', 'buyerName', 'buyer']),
                $this->firstStringValue($notice, ['deadline-date', 'deadlineDate']),
            ]))),
            'content' => json_encode($notice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        ];
    }

    private function usaidForecastToItem(array $row, array $source): array
    {
        $title = $this->firstStringValue($row, ['award_title', 'title', 'activity_name', 'name', 'description']);
        $publishedAt = $this->firstStringValue($row, ['date_updated', 'last_updated', 'modified_date', 'anticipated_solicitation_release_date', 'award_date']);
        $url = $this->firstStringValue($row, ['url', 'link', 'forecast_url']);

        return [
            'title' => $title ?: 'USAID Business Forecast opportunity',
            'url' => $url ?: (string) ($source['url'] ?? 'https://www.usaid.gov/business-forecast'),
            'domain' => (string) ($source['domain'] ?? 'usaid.gov'),
            'sourcecountry' => (string) ($source['name'] ?? 'USAID Business Forecast and Opportunities'),
            'seendate' => $publishedAt,
            'excerpt' => trim(implode(' | ', array_filter([
                $this->firstStringValue($row, ['description', 'award_description', 'sector', 'operating_unit']),
                $this->firstStringValue($row, ['country', 'countries', 'place_of_performance']),
                $this->firstStringValue($row, ['anticipated_solicitation_release_date', 'anticipated_award_date']),
            ]))),
            'content' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        ];
    }

    private function samGovOpportunityToItem(array $row, array $source): array
    {
        $noticeId = $this->firstStringValue($row, ['noticeId', 'noticeid', 'id']);
        $title = $this->firstStringValue($row, ['title', 'solicitationTitle']);
        $publishedAt = $this->firstStringValue($row, ['postedDate', 'publishedDate']);
        $url = $this->firstStringValue($row, ['uiLink', 'additionalInfoLink']);
        $descriptionUrl = $this->firstStringValue($row, ['description']);
        $contact = collect((array) ($row['pointOfContact'] ?? []))
            ->filter(fn ($contact) => is_array($contact))
            ->map(fn (array $contact) => trim(implode(' ', array_filter([
                $this->firstStringValue($contact, ['fullName', 'fullname']),
                $this->firstStringValue($contact, ['title']),
                $this->firstStringValue($contact, ['email']),
                $this->firstStringValue($contact, ['phone']),
            ]))))
            ->filter()
            ->implode(' | ');

        return [
            'title' => $title ?: 'SAM.gov contract opportunity',
            'url' => $url ?: ($noticeId !== '' ? 'https://sam.gov/opp/' . rawurlencode($noticeId) . '/view' : (string) ($source['url'] ?? 'https://sam.gov/content/opportunities')),
            'domain' => (string) ($source['domain'] ?? 'sam.gov'),
            'sourcecountry' => (string) ($source['name'] ?? 'SAM.gov Contract Opportunities'),
            'seendate' => $publishedAt,
            'excerpt' => trim(implode(' | ', array_filter([
                $this->firstStringValue($row, ['solicitationNumber']),
                $this->firstStringValue($row, ['type', 'baseType']),
                $this->firstStringValue($row, ['fullParentPathName', 'department', 'subTier', 'office']),
                $this->firstStringValue($row, ['responseDeadLine', 'archiveDate']),
                $this->firstStringValue($row, ['naicsCode', 'classificationCode']),
                $contact,
                $descriptionUrl,
            ]))),
            'content' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        ];
    }

    private function firstStringValue(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);

            if (is_string($value) && trim($value) !== '') {
                return $this->plainText($value);
            }

            if (is_array($value)) {
                $nested = $this->firstStringValue($value, ['en', 'eng', 'value', 'text', 'name', '0']);

                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        foreach ($data as $key => $value) {
            if (in_array($key, $keys, true) && is_scalar($value) && trim((string) $value) !== '') {
                return $this->plainText((string) $value);
            }

            if (is_array($value)) {
                $nested = $this->firstStringValue($value, $keys);

                if ($nested !== '') {
                    return $nested;
                }
            }
        }

        return '';
    }

    private function queryWorldBankProcurement(string $endpoint, array $countryConfig, string $countryName, string $term, string $focus, int $maxRecords): Collection
    {
        $request = Http::timeout(10)
            ->connectTimeout(5)
            ->acceptJson();

        if (! $this->crawlerSettingBoolean('verify_ssl', config('country_intelligence.verify_ssl', false))) {
            $request = $request->withoutVerifying();
        }

        try {
            $response = $request->get($endpoint, [
                'format' => 'json',
                'apilang' => 'en',
                'srce' => 'both',
                'rows' => min(10, $maxRecords),
                'os' => 0,
                'srt' => 'submission_deadline_date',
                'order' => 'desc',
                'qterm' => trim($countryName . ' ' . $term),
            ]);
        } catch (Throwable) {
            return collect();
        }

        if (! $response->ok()) {
            return collect();
        }

        return collect($response->json('procnotices', []))
            ->filter(fn (array $notice) => $this->worldBankNoticeMatchesCountry($notice, $countryConfig))
            ->filter(fn (array $notice) => $this->worldBankNoticeMatchesFocus($notice, $focus))
            ->filter(fn (array $notice) => $this->worldBankNoticeIsCurrentEnough($notice, $focus))
            ->map(fn (array $notice) => $this->worldBankNoticeToItem($notice));
    }

    private function worldBankNoticeMatchesCountry(array $notice, array $countryConfig): bool
    {
        $structuredCountryText = Str::lower(implode(' ', [
            (string) Arr::get($notice, 'project_ctry_name', ''),
            (string) Arr::get($notice, 'contact_ctry_name', ''),
        ]));

        $names = collect($countryConfig['search_names'] ?? [$countryConfig['name']])
            ->prepend((string) $countryConfig['name'])
            ->map(fn (string $name) => Str::lower($name))
            ->filter();

        return $structuredCountryText !== ''
            && $names->contains(fn (string $name) => Str::contains($structuredCountryText, $name));
    }

    private function worldBankNoticeMatchesFocus(array $notice, string $focus): bool
    {
        $bidText = Str::lower(implode(' ', [
            (string) Arr::get($notice, 'bid_description', ''),
        ]));

        if ($focus === 'hrms_tenders') {
            return $this->hasHrmsSubjectSignal($bidText);
        }

        if ($focus === 'sector_tenders') {
            return $this->hasSectorTenderSubjectSignal($bidText)
                && $this->hasSoftwareAdministrationOpportunitySignal($bidText)
                && ! $this->hasExcludedSectorTenderSignal($bidText);
        }

        if ($focus === 'social_security') {
            $hasSubject = Str::contains($bidText, [
                'social security',
                'social protection',
                'protection sociale',
                'securite sociale',
                'sécurité sociale',
                'pension',
                'pensions',
                'provident fund',
                'retraite',
                'ipres',
                'css',
                'caisse de securite sociale',
                'caisse de sécurité sociale',
            ]);
            $hasOperationalOpportunity = Str::contains($bidText, [
                'administration',
                'administrative',
                'management system',
                'information system',
                'software',
                'platform',
                'digital',
                'digitization',
                'digitalization',
                'registry',
                'registration',
                'benefit payment',
                'benefits administration',
                'pension administration',
                'contribution collection',
                'contributions',
                'claims processing',
                'case management',
            ]);

            return $hasSubject && $hasOperationalOpportunity;
        }

        $terms = collect(config("country_intelligence.focuses.$focus.terms", []))
            ->merge(config("country_intelligence.focuses.$focus.strong_signals", []))
            ->map(fn (string $term) => Str::lower($term));

        return $terms->contains(fn (string $term) => $term !== '' && Str::contains($bidText, $term));
    }

    private function worldBankNoticeIsCurrentEnough(array $notice, string $focus): bool
    {
        if (! in_array($focus, ['hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders', 'social_security'], true)) {
            return true;
        }

        $deadline = $this->parseGdeltDate((string) Arr::get($notice, 'submission_deadline_date', ''));

        if ($deadline && $deadline->gte(now()->subDays(14))) {
            return true;
        }

        $publishedAt = collect([
            Arr::get($notice, 'submission_date', ''),
            Arr::get($notice, 'noticedate', ''),
        ])
            ->map(fn ($value) => $this->parseGdeltDate((string) $value))
            ->filter()
            ->sortDesc()
            ->first();

        return $publishedAt
            ? $publishedAt->gte(now()->subDays((int) $this->crawlerSetting('world_bank_recent_notice_days', config('country_intelligence.world_bank_recent_notice_days', 45))))
            : false;
    }

    private function worldBankNoticeToItem(array $notice): array
    {
        $id = (string) Arr::get($notice, 'id', '');
        $description = (string) Arr::get($notice, 'bid_description', '');
        $noticeType = (string) Arr::get($notice, 'notice_type', '');
        $title = trim($description) ?: trim($noticeType . ' - ' . (string) Arr::get($notice, 'project_name', ''));
        $url = $id !== ''
            ? 'https://projects.worldbank.org/en/projects-operations/procurement-detail/' . rawurlencode($id)
            : 'https://projects.worldbank.org/en/projects-operations/opportunities';
        $summaryParts = array_filter([
            $noticeType,
            (string) Arr::get($notice, 'project_ctry_name', ''),
            (string) Arr::get($notice, 'project_name', ''),
            (string) Arr::get($notice, 'procurement_method_name', ''),
            (string) Arr::get($notice, 'notice_text', ''),
        ]);

        return [
            'title' => $title,
            'url' => $url,
            'domain' => 'search.worldbank.org',
            'sourcecountry' => 'World Bank Procurement Notices',
            'seendate' => (string) Arr::get($notice, 'submission_deadline_date', Arr::get($notice, 'submission_date', Arr::get($notice, 'noticedate', ''))),
            'excerpt' => implode(' | ', $summaryParts),
            'content' => (string) Arr::get($notice, 'notice_text', ''),
        ];
    }

    private function knownItems(array $countryConfig, string $focus): Collection
    {
        return collect($countryConfig['known_items'][$focus] ?? [])->map(function (array $item) {
            return [
                'title' => $item['title'] ?? '',
                'url' => $item['source_url'] ?? '',
                'domain' => parse_url((string) ($item['source_url'] ?? ''), PHP_URL_HOST) ?: '',
                'sourcecountry' => $item['source_name'] ?? '',
                'seendate' => $item['publication_date'] ?? '',
                'excerpt' => $item['summary'] ?? '',
                'known_relevance_score' => $item['relevance_score'] ?? null,
            ];
        });
    }

    private function normalizeItem(array $item, array $countryConfig, string $focus): array
    {
        $title = trim((string) Arr::get($item, 'title', ''));
        $url = trim((string) Arr::get($item, 'url', ''));
        $domain = trim((string) Arr::get($item, 'domain', parse_url($url, PHP_URL_HOST) ?: ''));
        $sourceName = $this->sourceNameForDomain($domain, $countryConfig) ?: trim((string) Arr::get($item, 'sourcecountry', ''));
        $publishedAt = $this->parseGdeltDate((string) Arr::get($item, 'seendate', ''));
        $sourceText = $this->plainText(trim((string) Arr::get($item, 'excerpt', '') . ' ' . (string) Arr::get($item, 'content', '')));
        $focusLabel = config("country_intelligence.focuses.$focus.label", 'Country Intelligence');
        $matchText = $title . ' ' . $domain . ' ' . $sourceText;
        $summary = $this->englishSummary($focus, $matchText, $countryConfig, $sourceName ?: $domain);
        $summary = '[' . $focusLabel . '] ' . $summary;
        $englishTitle = $this->translator->toEnglish($title ?: $url, $url, (string) ($countryConfig['name'] ?? ''), $sourceName ?: $domain);
        $englishTitle = $this->translatedTitleOrEmpty($englishTitle, $title ?: $url);
        $englishSummary = $this->translator->summaryToEnglish($summary, $url, (string) ($countryConfig['name'] ?? ''), $sourceName ?: $domain);

        return [
            'title' => Str::limit($title ?: $url, 500, ''),
            'title_english' => Str::limit($englishTitle, 500, ''),
            'title_original' => Str::limit($title ?: $url, 500, ''),
            'source_name' => Str::limit($sourceName ?: $domain, 255, ''),
            'source_url' => Str::limit($url, 1000, ''),
            'publication_date' => $publishedAt?->toDateString(),
            'summary' => $summary,
            'summary_english' => $englishSummary,
            'raw_match_text' => $matchText,
            'relevance_score' => (float) (Arr::get($item, 'known_relevance_score') ?? $this->relevanceScore($matchText, $focus)),
        ];
    }

    private function translatedTitleOrEmpty(string $englishTitle, string $originalTitle): string
    {
        $englishTitle = trim($englishTitle);
        $originalTitle = trim($originalTitle);

        if ($englishTitle === '' || $originalTitle === '') {
            return $englishTitle;
        }

        if ($englishTitle === $originalTitle && TitleLanguage::looksNonEnglish($originalTitle)) {
            return '';
        }

        return $englishTitle;
    }

    private function englishSummary(string $focus, string $matchText, array $countryConfig, string $sourceName): string
    {
        $countryName = (string) ($countryConfig['name'] ?? 'the selected country');
        $sourceName = trim($sourceName) ?: 'the original source';
        $themes = $this->matchedThemes($matchText, $focus);
        $themeText = $themes->isNotEmpty()
            ? ' Matched themes: ' . $themes->take(5)->implode(', ') . '.'
            : '';

        if ($focus === 'sector_tenders') {
            return 'Potential sector tender or procurement opportunity for ' . $countryName . ' from ' . $sourceName . '.' . $themeText . ' The original notice or article is kept as the source link and may be in the local language.';
        }

        if ($focus === 'hrms_tenders') {
            return 'Potential HRMS, payroll, HCM, or human resources technology tender for ' . $countryName . ' from ' . $sourceName . '.' . $themeText . ' The original notice or article is kept as the source link and may be in the local language.';
        }

        if ($focus === 'erms_tenders') {
            return 'Potential enterprise risk management, GRC, compliance, or audit management technology tender for ' . $countryName . ' from ' . $sourceName . '.' . $themeText . ' The original notice or article is kept as the source link and may be in the local language.';
        }

        if ($focus === 'ebpc_tenders') {
            return 'Potential budgeting, budget planning, budget control, IFMIS, or public financial management technology tender for ' . $countryName . ' from ' . $sourceName . '.' . $themeText . ' The original notice or article is kept as the source link and may be in the local language.';
        }

        return 'Potential social security, pensions, labour, or related procurement update for ' . $countryName . ' from ' . $sourceName . '.' . $themeText . ' The original notice or article is kept as the source link and may be in the local language.';
    }

    private function matchedThemes(string $text, string $focus): Collection
    {
        $text = Str::lower($text);
        if ($focus === 'erms_tenders') {
            return collect([
                'enterprise risk management' => ['enterprise risk management', 'risk management software', 'risk management system', 'erm software', 'erms'],
                'GRC/compliance' => ['grc software', 'governance risk compliance', 'governance, risk and compliance', 'compliance management system'],
                'audit/controls' => ['audit management system', 'internal audit software', 'internal control system'],
                'risk register/analytics' => ['risk register', 'operational risk management', 'risk analytics', 'loss event database', 'incident management system'],
            ])
                ->filter(fn (array $signals) => Str::contains($text, $signals))
                ->keys()
                ->values();
        }

        $themeMap = $focus === 'sector_tenders'
            ? [
                'telecom' => ['telecom', 'telecommunications', 'mobile network', 'broadband', 'fiber optic', 'fibre optic', 'اتصالات', '电信', 'โทรคมนาคม'],
                'oil and gas' => ['oil and gas', 'oil & gas', 'petroleum', 'natural gas', 'lng', 'pipeline', 'نفط', 'غاز', '石油', '天然气'],
                'postal services' => ['postal', 'post office', 'mail service', 'خدمات بريدية', '邮政'],
                'civil service/public administration' => ['civil service', 'public administration', 'public service', 'government administration', 'الخدمة المدنية', '公共行政'],
                'airlines/aviation' => ['airline', 'airlines', 'aviation', 'airport', 'civil aviation', 'طيران', 'مطارات', '航空', '机场'],
                'mining' => ['mining', 'minerals', 'mineral resources', 'تعدين', '采矿'],
                'banking and finance' => ['banking', 'financial services', 'central bank', 'treasury', 'bank', 'مصارف', 'بنوك', '金融', '银行'],
                'tender/procurement' => ['tender', 'procurement', 'rfp', 'request for proposal', 'bid', 'مناقصة', 'مشتريات', '招标', '采购'],
            ]
            : [
                'tender/procurement' => ['tender', 'procurement', 'rfp', 'request for proposal', 'bid', 'مناقصة', 'مشتريات'],
                'social security/pensions' => ['social security', 'pension', 'provident fund', 'التأمينات الاجتماعية', 'التقاعد'],
                'HRMS/payroll' => ['hrms', 'payroll', 'hcm', 'human resource', 'الموارد البشرية', 'الرواتب'],
            ];

        return collect($themeMap)
            ->filter(fn (array $signals) => Str::contains($text, $signals))
            ->keys()
            ->values();
    }

    private function sourceNameForDomain(string $domain, array $countryConfig): ?string
    {
        $domain = Str::of($domain)->lower()->replace('www.', '')->toString();

        foreach ($countryConfig['sources'] ?? [] as $source) {
            $sourceDomain = Str::of($source['domain'] ?? '')->lower()->replace('www.', '')->toString();

            if ($sourceDomain && Str::contains($domain, $sourceDomain)) {
                return $source['name'];
            }
        }

        foreach ($this->developmentPartnerTenderSources() as $source) {
            $sourceDomain = Str::of($source['domain'] ?? '')->lower()->replace('www.', '')->toString();

            if ($sourceDomain && Str::contains($domain, $sourceDomain)) {
                return $source['name'];
            }
        }

        return null;
    }

    private function developmentPartnerTenderSources(): Collection
    {
        return collect(config('country_intelligence.development_partner_sources', []))
            ->merge($this->databaseGlobalTenderSources())
            ->filter(fn (array $source) => ! empty($source['domain']))
            ->unique(fn (array $source) => Str::lower((string) ($source['domain'] ?? '')) . '|' . Str::lower((string) ($source['url'] ?? '')))
            ->values();
    }

    private function hrmsTargetIndustryTerms(): Collection
    {
        return collect(config('country_intelligence.focuses.hrms_tenders.industry_contexts', []))
            ->filter()
            ->values();
    }

    private function isRelevant(array $item, string $focus): bool
    {
        $text = Str::lower((string) ($item['raw_match_text'] ?? ($item['title'] . ' ' . $item['summary'] . ' ' . $item['source_url'])));

        if ($focus === 'hrms_tenders') {
            return $this->hasHrmsSubjectSignal($text)
                && $this->hasTenderSignal($text)
                && $item['relevance_score'] >= 2.5;
        }

        if ($focus === 'erms_tenders') {
            return $this->hasErmsSubjectSignal($text)
                && ! $this->hasExcludedErmsTenderSignal($text)
                && $this->hasTenderSignal($text)
                && $item['relevance_score'] >= 2.5;
        }

        if ($focus === 'ebpc_tenders') {
            return $this->hasEbpcSubjectSignal($text)
                && $this->hasTenderSignal($text)
                && $item['relevance_score'] >= 2.5;
        }

        if ($focus === 'sector_tenders') {
            return $this->hasSectorTenderSubjectSignal($text)
                && $this->hasHrmsSubjectSignal($text)
                && $this->hasTenderSignal($text)
                && ! $this->hasExcludedSectorTenderSignal($text)
                && $item['relevance_score'] >= 2.5;
        }

        return $this->hasSocialSecuritySubjectSignal($text)
            && $item['relevance_score'] >= 2.0;
    }

    private function itemMatchesCountry(array $item, array $countryConfig): bool
    {
        $text = Str::lower(implode(' ', [
            $item['title'] ?? '',
            $item['summary'] ?? '',
            $item['source_url'] ?? '',
            $item['source_name'] ?? '',
        ]));

        $countryNames = collect($countryConfig['search_names'] ?? [$countryConfig['name']])
            ->prepend((string) ($countryConfig['name'] ?? ''))
            ->map(fn (string $name) => Str::lower(trim($name)))
            ->filter(fn (string $name) => strlen($name) >= 4)
            ->unique();

        if ($countryNames->contains(fn (string $name) => Str::contains($text, $name))) {
            return true;
        }

        $host = Str::of(parse_url((string) ($item['source_url'] ?? ''), PHP_URL_HOST) ?: '')
            ->lower()
            ->replace('www.', '')
            ->toString();

        return collect($countryConfig['sources'] ?? [])
            ->pluck('domain')
            ->map(fn (?string $domain) => Str::of((string) $domain)->lower()->replace('www.', '')->toString())
            ->filter()
            ->contains(fn (string $domain) => $host !== '' && Str::contains($host, $domain));
    }

    private function hasTenderSignal(string $text): bool
    {
        return Str::contains($text, [
            'tender',
            'procurement',
            'rfp',
            'request for proposal',
            'request for expression of interest',
            'expression of interest',
            'request for bids',
            'invitation for bids',
            'bid',
            'proposal',
            'contract',
            'مناقصة',
            'مناقصات',
            'عطاء',
            'عطاءات',
            'مشتريات',
            'طلب عروض',
            '招标',
            '采购',
            '投标',
            '入札',
            '調達',
            '입찰',
            '조달',
            'ประกวดราคา',
            'จัดซื้อจัดจ้าง',
            'đấu thầu',
            'mua sắm',
            'pengadaan',
            'perolehan',
            'ihale',
            'مناقصه',
            'تدارکات',
            'ٹینڈر',
            'خریداری',
            'দরপত্র',
            'निविदा',
        ]);
    }

    private function hasHrmsSubjectSignal(string $text): bool
    {
        return Str::contains($text, [
            'hrms',
            'hcm',
            'payroll',
            'human resource management system',
            'human resources management system',
            'human resource information system',
            'human resources information system',
            'hrmis',
            'hrims',
            'workforce management system',
            'employee self-service',
            'employee self service',
            'personnel management system',
            'talent management',
            'talent management system',
            'performance management',
            'performance management system',
            'learning management',
            'learning management system',
            'benefits administration',
            'benefits administration system',
            'time and attendance',
            'time and attendance system',
        ]);
    }

    private function hasSectorTenderSubjectSignal(string $text): bool
    {
        return Str::contains($text, [
            'telecom',
            'telecommunications',
            'mobile network',
            'broadband',
            'fiber optic',
            'fibre optic',
            'ict infrastructure',
            'oil and gas',
            'oil & gas',
            'petroleum',
            'natural gas',
            'lng',
            'pipeline',
            'postal service',
            'postal services',
            'post office',
            'mail service',
            'civil service',
            'public service',
            'public administration',
            'government administration',
            'airline',
            'airlines',
            'aviation',
            'airport',
            'civil aviation',
            'mining',
            'minerals',
            'mineral resources',
            'banking',
            'financial services',
            'central bank',
            'treasury',
            'tax administration',
            'customs',
            'اتصالات',
            'نفط وغاز',
            'النفط والغاز',
            'خدمات بريدية',
            'البريد',
            'الخدمة المدنية',
            'الإدارة العامة',
            'طيران',
            'مطارات',
            'تعدين',
            'مصارف',
            'بنوك',
            'خدمات مالية',
            '电信',
            '石油天然气',
            '邮政服务',
            '公务员',
            '公共行政',
            '航空',
            '机场',
            '采矿',
            '银行',
            '金融服务',
            '中央银行',
        ]);
    }

    private function hasErmsSubjectSignal(string $text): bool
    {
        return Str::contains($text, [
            'enterprise risk management',
            'risk management software',
            'risk management system',
            'erm software',
            'erms',
            'grc software',
            'governance risk compliance',
            'governance, risk and compliance',
            'compliance management system',
            'audit management system',
            'internal audit software',
            'internal control system',
            'risk register',
            'operational risk management',
        ]);
    }

    private function hasExcludedErmsTenderSignal(string $text): bool
    {
        $text = Str::lower($text);

        if (Str::contains($text, [
            'detailed engineering design',
            'engineering design',
            'technical specifications',
            'cost estimates',
            'construction supervision',
            'road infrastructure',
            'school and health infrastructure',
            'renovation and expansion',
            'climate-resilient road',
        ])) {
            return true;
        }

        $procurementIntegrityOnly = Str::contains($text, [
            'bid-rigging',
            'bid rigging',
            'public procurement procedures',
            'procurement integrity',
            'anti-corruption',
            'anticorruption',
        ]);

        if ($procurementIntegrityOnly && ! Str::contains($text, [
            'enterprise risk management',
            'risk management software',
            'risk management system',
            'erm software',
            'erms',
            'grc software',
            'compliance management system',
            'audit management system',
            'internal audit software',
            'risk register',
        ])) {
            return true;
        }

        return false;
    }

    private function hasEbpcSubjectSignal(string $text): bool
    {
        return Str::contains($text, [
            'budgeting software',
            'budget planning software',
            'budget preparation software',
            'budget control software',
            'budget management system',
            'budget management information system',
            'integrated financial management information system',
            'financial management information system',
            'public financial management information system',
            'public financial management system',
            'ifmis',
            'fmis',
            'pfmis',
            'budget preparation system',
            'budget formulation system',
            'budget execution system',
            'budget monitoring system',
            'budget planning system',
            'budget and planning system',
            'budget module',
            'budget preparation module',
            'budget execution module',
            'program based budgeting',
            'programme based budgeting',
            'performance based budgeting',
            'financial planning software',
            'forecasting software',
            'medium term expenditure framework',
            'mtef',
        ]);
    }

    private function hasSoftwareAdministrationOpportunitySignal(string $text): bool
    {
        return Str::contains($text, [
            'hrms',
            'hris',
            'hcm',
            'payroll',
            'human resource management system',
            'human resources management system',
            'human resource information system',
            'human resources information system',
            'pension software',
            'pension administration',
            'benefits administration',
            'benefit administration',
            'social security administration',
            'social insurance administration',
            'administration system',
            'management information system',
            'information management system',
            'information system',
            'software',
            'platform',
            'digital platform',
            'digital transformation',
            'digitization',
            'digitalisation',
            'e-government',
            'egovernment',
            'e-services',
            'case management',
            'workflow management',
            'registry',
            'registration system',
            'licensing system',
            'tax administration system',
            'customs management system',
            'core banking',
            'banking system',
            'financial management information system',
            'fmis',
            'erp',
            'crm',
            'customer relationship management',
            'billing system',
            'revenue management system',
            'document management system',
            'records management system',
            'portal',
            'mobile application',
            'web application',
            'database',
            'data warehouse',
            'business intelligence',
            'analytics platform',
            'cybersecurity',
            'identity management',
            'biometric',
        ]);
    }

    private function hasExcludedSectorTenderSignal(string $text): bool
    {
        return Str::contains($text, [
            'vocational training',
            'skills training',
            'training service provider',
            'technical assistant',
            'technical assistance',
            'individual consultant',
            'project manager',
            'vocational skills',
            'capacity building only',
            'civil works',
            'construction',
            'rehabilitation works',
            'road works',
            'building works',
            'design & build work',
            'bio mining',
            'biomining',
            'bio remediation',
            'bioremediation',
            'waste management works',
            'groundwater',
            'water supply',
            'brine discharge',
            'geographic information system',
            'geo-information system',
            'gis platform',
            'broadband map',
            'surface and sub-surface facilities',
            'supply of vehicles',
            'supply of equipment',
            'hardware supply',
            'network implementation',
            'ict infrastructure',
            'credit registry',
            'treasury platform',
            'e-banking',
            'm-banking',
            'office furniture',
            'stationery',
            'catering',
            'security guard',
            'cleaning services',
        ]);
    }

    private function hasSocialSecuritySubjectSignal(string $text): bool
    {
        if (Str::contains($text, ['national insurance property development', 'nipdec'])) {
            return false;
        }

        return Str::contains($text, [
            'social security',
            'social insurance',
            'national insurance board',
            'national insurance scheme',
            'national insurance service',
            'national insurance services',
            'national insurance corporation',
            'social protection',
            'protection sociale',
            'securite sociale',
            'sécurité sociale',
            'seguridad social',
            'seguranca social',
            'previdencia social',
            'pension',
            'pensions',
            'pension administration',
            'pension fund',
            'pension funds',
            'pension system',
            'pensions administration',
            'pension reform',
            'pension payment',
            'pension payments',
            'provident fund',
            'provident funds',
            'benefits administration',
            'benefit payments',
            'contribution',
            'contributions',
            'retirement',
            'retirement benefits',
            'old age benefit',
            'old-age benefit',
            'caisse de securite sociale',
            'caisse de sécurité sociale',
            'ipres',
            'nssf',
            'cnss',
            'inss',
            'social security board',
            'social security administration',
        ]);
    }

    private function relevanceScore(string $text, string $focus): float
    {
        $text = Str::lower($text);
        $score = 0.0;

        foreach (config("country_intelligence.focuses.$focus.terms", config('country_intelligence.topics', [])) as $topic) {
            if (Str::contains($text, Str::lower($topic))) {
                $score += in_array($topic, ['tenders', 'procurement', 'rfp', 'request for proposal', 'expression of interest'], true) ? 1.5 : 1.0;
            }
        }

        foreach (config("country_intelligence.focuses.$focus.strong_signals", []) as $signal) {
            if (Str::contains($text, $signal)) {
                $score += 1.0;
            }
        }

        return min(10.0, $score);
    }

    private function parseGdeltDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('YmdHis', $value);
        } catch (\Throwable) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function focusKey(string $focus): string
    {
        return array_key_exists($focus, config('country_intelligence.focuses', [])) ? $focus : 'social_security';
    }

    private function storeUpdate(Country $country, ?CountryTopic $topic, array $item): CountryUpdate
    {
        $sourceUrl = (string) $item['source_url'];
        $fingerprint = $this->sourceFingerprint($sourceUrl);

        $existing = CountryUpdate::query()
            ->where('country_id', $country->id)
            ->where(function ($query) use ($sourceUrl, $fingerprint) {
                $query->where('source_url', $sourceUrl);

                if ($fingerprint !== null) {
                    $query->orWhere('source_fingerprint', $fingerprint);
                }
            })
            ->orderByRaw("CASE WHEN review_status = 'rejected' THEN 0 WHEN map_processed_at IS NOT NULL THEN 1 WHEN archive_read_at IS NOT NULL THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->first();

        $payload = [
            'country_id' => $country->id,
            'country_topic_id' => $topic?->id,
            'title' => $item['title'],
            'title_english' => $item['title_english'] ?? $item['title'],
            'title_original' => $item['title_original'] ?? $item['title'],
            'source_name' => $item['source_name'],
            'source_url' => $sourceUrl,
            'source_fingerprint' => $fingerprint,
            'publication_date' => $item['publication_date'],
            'retrieved_at' => now(),
            'summary' => $item['summary'],
            'summary_english' => $item['summary_english'] ?? null,
            'relevance_score' => $item['relevance_score'],
        ];

        if ($existing) {
            if ($existing->review_status === 'rejected' || $existing->map_processed_at || $existing->archive_read_at) {
                if ($fingerprint !== null && blank($existing->source_fingerprint)) {
                    $existing->forceFill(['source_fingerprint' => $fingerprint])->save();
                }

                return $existing;
            }

            $existing->fill($payload)->save();

            return $existing;
        }

        return CountryUpdate::query()->create($payload + [
            'review_status' => 'unreviewed',
        ]);
    }

    private function sourceFingerprint(string $url): ?string
    {
        $normalized = $this->normalizeSourceUrlForFingerprint($url);

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    private function normalizeSourceUrlForFingerprint(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return Str::lower(rtrim($url, "/ \t\n\r\0\x0B"));
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? 'https'));
        $host = Str::lower((string) $parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        $path = rtrim($path, '/') ?: '/';

        $queryString = '';
        if (filled($parts['query'] ?? null)) {
            parse_str((string) $parts['query'], $query);
            $dropKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'mc_cid', 'mc_eid', 'oc', 'cid'];
            foreach ($dropKeys as $key) {
                unset($query[$key]);
            }
            ksort($query);
            $queryString = http_build_query($query);
        }

        return $scheme . '://' . $host . $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    private function storeMonitorRun(Country $country, string $focus, array $sourcesChecked, int $itemsFound, Carbon $startedAt): CountryMonitorRun
    {
        return CountryMonitorRun::query()->create([
            'country_id' => $country->id,
            'focus' => $focus,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'sources_checked' => array_values(array_filter($sourcesChecked)),
            'items_found' => $itemsFound,
            'status' => 'completed',
        ]);
    }
}
