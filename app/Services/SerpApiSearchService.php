<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryUpdate;
use App\Models\CrawlerSetting;
use App\Support\CountryUpdateDedupeRules;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SerpApiSearchService
{
    public function run(array $parameters, ?callable $onItem = null): array
    {
        $apiKey = $this->settingString('serpapi_key', env('SERPAPI_KEY', ''));

        if ($apiKey === '') {
            throw new \RuntimeException('SerpAPI key is not configured.');
        }

        $countries = Country::query()
            ->whereIn('iso_code', array_map('strtoupper', (array) ($parameters['countries'] ?? [])))
            ->orderBy('name')
            ->get();

        $keywords = collect($parameters['keywords'] ?? [])
            ->map(fn ($keyword) => trim((string) $keyword))
            ->filter()
            ->values();
        $queryTemplate = trim((string) ($parameters['query_template'] ?? ''));
        $resultsPerCountry = max(1, min(20, (int) ($parameters['results_per_country'] ?? 10)));
        $dryRun = (bool) ($parameters['dry_run'] ?? true);
        $capture = (bool) ($parameters['capture'] ?? true);
        $focus = (string) ($parameters['focus'] ?? 'social_security');
        $keywordSearchMode = (string) ($parameters['keyword_search_mode'] ?? 'grouped');
        $keywordGroups = $keywords->isEmpty()
            ? collect([[]])
            : ($keywordSearchMode === 'per_keyword'
                ? $keywords->map(fn (string $keyword) => [$keyword])->values()
                : collect([$keywords->all()]));

        $summary = [
            'countries' => $countries->count(),
            'queries' => 0,
            'results' => 0,
            'captured' => 0,
            'duplicates' => 0,
            'filtered_out' => 0,
            'errors' => 0,
        ];
        $items = [];

        foreach ($countries as $country) {
            foreach ($keywordGroups as $keywordGroup) {
                $query = $this->buildQuery($queryTemplate, $keywordGroup, $country);
                $summary['queries']++;
                $keywordLabel = collect($keywordGroup)->implode(' | ');

                try {
                    $results = $this->search($apiKey, $query, $resultsPerCountry);
                } catch (Throwable $exception) {
                    $summary['errors']++;
                    $item = [
                        'country_id' => $country->id,
                        'country' => $country->name,
                        'iso_code' => $country->iso_code,
                        'query' => $query,
                        'keyword' => $keywordLabel,
                        'status' => 'error',
                        'error' => $exception->getMessage(),
                        'searched_at' => now()->toDateTimeString(),
                    ];
                    $items[] = $item;
                    $onItem ? $onItem($item, $summary, $items) : null;

                    continue;
                }

                foreach ($results as $result) {
                    $sourceUrl = (string) ($result['link'] ?? '');
                    $title = trim((string) ($result['title'] ?? ''));
                    $sourceName = trim((string) ($result['source'] ?? parse_url($sourceUrl, PHP_URL_HOST) ?: 'SerpAPI result'));
                    $snippet = trim((string) ($result['snippet'] ?? ''));
                    $resultFilter = $this->tenderResultFilter($title, $snippet, $sourceUrl, $keywordGroup, $country);

                    if (! $resultFilter['keep']) {
                        $summary['filtered_out']++;
                        $item = [
                            'country_id' => $country->id,
                            'country' => $country->name,
                            'iso_code' => $country->iso_code,
                            'query' => $query,
                            'keyword' => $keywordLabel,
                            'status' => 'filtered',
                            'filter_reason' => $resultFilter['reason'],
                            'title' => $title,
                            'source_name' => $sourceName,
                            'source_url' => $sourceUrl,
                            'snippet' => $snippet,
                            'publication_date' => $this->parseResultDate((string) ($result['date'] ?? '')),
                            'country_update_id' => null,
                            'searched_at' => now()->toDateTimeString(),
                        ];
                        $items[] = $item;
                        $onItem ? $onItem($item, $summary, $items) : null;

                        continue;
                    }

                    $summary['results']++;
                    $publicationDate = $this->parseResultDate((string) ($result['date'] ?? ''));
                    $countryUpdateId = null;
                    $status = 'found';

                    if ($capture && ! $dryRun && $sourceUrl !== '' && $title !== '') {
                        $captured = $this->captureCountryUpdate($country, $focus, $title, $sourceName, $sourceUrl, $snippet, $publicationDate);
                        $countryUpdateId = $captured['country_update_id'];
                        $status = $captured['status'];

                        if ($status === 'captured') {
                            $summary['captured']++;
                        } elseif ($status === 'duplicate') {
                            $summary['duplicates']++;
                        }
                    }

                    $item = [
                        'country_id' => $country->id,
                        'country' => $country->name,
                        'iso_code' => $country->iso_code,
                        'query' => $query,
                        'keyword' => $keywordLabel,
                        'status' => $status,
                        'title' => $title,
                        'source_name' => $sourceName,
                        'source_url' => $sourceUrl,
                        'snippet' => $snippet,
                        'publication_date' => $publicationDate,
                        'country_update_id' => $countryUpdateId,
                        'searched_at' => now()->toDateTimeString(),
                    ];
                    $items[] = $item;
                    $onItem ? $onItem($item, $summary, $items) : null;
                }
            }
        }

        return $summary + ['items' => $items];
    }

    private function buildQuery(string $template, array $keywords, Country $country): string
    {
        $keywordsText = collect($keywords)->map(fn (string $keyword) => '"' . $keyword . '"')->implode(' OR ');

        if ($template === '') {
            $template = '"{country}" ({keywords})';
        }

        return trim(str_replace(
            ['{country}', '{iso}', '{keywords}'],
            [(string) $country->name, (string) $country->iso_code, $keywordsText],
            $template
        ));
    }

    private function search(string $apiKey, string $query, int $limit): array
    {
        $request = Http::timeout(30)->connectTimeout(8);

        if (! $this->settingBoolean('serpapi_verify_ssl', env('SERPAPI_VERIFY_SSL', true))) {
            $request = $request->withoutVerifying();
        }

        $response = $request->get('https://serpapi.com/search.json', [
            'engine' => 'google',
            'q' => $query,
            'num' => $limit,
            'api_key' => $apiKey,
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('SerpAPI HTTP ' . $response->status() . ': ' . Str::limit($response->body(), 220));
        }

        return collect($response->json('organic_results') ?? [])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $keywords
     * @return array{keep: bool, reason: string}
     */
    private function tenderResultFilter(string $title, string $snippet, string $sourceUrl, array $keywords, Country $country): array
    {
        $haystack = Str::lower($title . ' ' . $snippet . ' ' . $sourceUrl);
        $host = Str::lower((string) parse_url($sourceUrl, PHP_URL_HOST));
        $path = Str::lower((string) parse_url($sourceUrl, PHP_URL_PATH));
        $countryName = Str::lower((string) $country->name);
        $countryIso = Str::lower((string) $country->iso_code);
        $countrySlug = Str::slug((string) $country->name);

        $blockedDomains = [
            'adp.com',
            'apple.com',
            'bluebisonsoftware.com',
            'capterra.',
            'darwinbox.com',
            'employmenthero.com',
            'facebook.com',
            'flaxem.com',
            'focussoftnet.com',
            'hibob.com',
            'instagram.com',
            'g2.com',
            'getapp.',
            'lattice.com',
            'leverx.com',
            'linkedin.com',
            'paylocity.com',
            'softwareadvice.',
            'sourceforge.',
            'selecthub.',
            'trustradius.',
            'saasworthy.',
            'softwaresuggest.',
            'ramco.com',
            'reddit.com',
            'rsmus.com',
            'peoplemanagingpeople.',
            'triblockhr.com',
            'techradar.',
            'forbes.com',
            'workzoom.com',
            'youtube.com',
        ];

        if (Str::contains($host, $blockedDomains)) {
            return ['keep' => false, 'reason' => 'vendor directory or software review domain'];
        }

        if (Str::contains($path, ['/keywords/', '/keyword/'])
            || preg_match('/\blatest\b.*\btenders?\b.*\b20\d{2}\b/i', $title)
            || Str::contains($haystack, ['government & private tenders', 'online active and archive database', 'sourced directly from reliable government portals'])) {
            return ['keep' => false, 'reason' => 'tender listing or keyword index page'];
        }

        if (Str::contains($haystack, ['rfp template', 'request for proposal template', 'invite ', 'book a demo', 'glossary'])) {
            return ['keep' => false, 'reason' => 'vendor RFP/template or marketing page'];
        }

        if (Str::contains($path, '/government-tenders/')) {
            $segments = collect(explode('/', trim($path, '/')))->values();
            $countrySegment = $segments->first(fn (string $segment, int $index) => $index > 0 && $segments->get($index - 1) === 'government-tenders');

            if ($countrySegment && $countrySegment !== $countrySlug) {
                return ['keep' => false, 'reason' => 'tender page belongs to a different country'];
            }
        }

        $procurementSignals = [
            'tender',
            'rfp',
            'rfi',
            'rfq',
            'eoi',
            'request for proposal',
            'request for proposals',
            'request for information',
            'request for quotation',
            'expression of interest',
            'invitation to bid',
            'invitation for bid',
            'invitation for bids',
            'invitation to tender',
            'bid notice',
            'bidding document',
            'bidding documents',
            'procurement notice',
            'contract notice',
            'solicitation',
            'terms of reference',
            'consulting services',
            'notice inviting',
        ];

        if (! Str::contains($haystack, $procurementSignals)) {
            return ['keep' => false, 'reason' => 'missing tender/RFP intent'];
        }

        if (! Str::contains($haystack, [$countryName, $countryIso])) {
            return ['keep' => false, 'reason' => 'missing selected country'];
        }

        $keywordNeedles = collect($keywords)
            ->flatMap(fn (string $keyword) => [$keyword, str_replace(' software', '', $keyword)])
            ->map(fn (string $keyword) => Str::lower(trim($keyword)))
            ->filter(fn (string $keyword) => strlen($keyword) >= 4)
            ->unique()
            ->values()
            ->all();

        if ($keywordNeedles !== [] && ! Str::contains($haystack, $keywordNeedles)) {
            return ['keep' => false, 'reason' => 'missing selected product keyword'];
        }

        $vendorSignals = [
            'pricing',
            'free trial',
            'book a demo',
            'request a demo',
            'schedule a demo',
            'features',
            'compare',
            'alternatives',
            'reviews',
            'best ',
            'top ',
            'buyer guide',
            'case study',
            'what is ',
            'our software',
            'software solution for',
            'software solutions for',
        ];

        if (Str::contains($haystack, $vendorSignals) && ! Str::contains($haystack, ['tender', 'rfp', 'rfi', 'rfq', 'eoi'])) {
            return ['keep' => false, 'reason' => 'vendor marketing page'];
        }

        return ['keep' => true, 'reason' => 'matched tender/RFP intent'];
    }

    private function captureCountryUpdate(Country $country, string $focus, string $title, string $sourceName, string $sourceUrl, string $snippet, ?string $publicationDate): array
    {
        $fingerprint = CountryUpdateDedupeRules::sourceFingerprint($sourceUrl);
        $payload = [
            'country_id' => $country->id,
            'title' => Str::limit($title, 500, ''),
            'title_english' => Str::limit($title, 500, ''),
            'title_original' => Str::limit($title, 500, ''),
            'source_name' => Str::limit($sourceName, 255, ''),
            'source_url' => Str::limit($sourceUrl, 1000, ''),
            'source_fingerprint' => $fingerprint,
            'publication_date' => $publicationDate,
            'retrieved_at' => now(),
            'summary' => '[' . $focus . '] SerpAPI captured result. ' . Str::limit($snippet, 1800, ''),
            'summary_english' => Str::limit($snippet, 2000, ''),
            'relevance_score' => 3.0,
        ];

        $existing = CountryUpdate::query()
            ->where('country_id', $country->id)
            ->where(function ($query) use ($sourceUrl, $fingerprint) {
                $query->where('source_url', $sourceUrl);

                if ($fingerprint) {
                    $query->orWhere('source_fingerprint', $fingerprint);
                }
            })
            ->first();

        if (! $existing) {
            $keys = CountryUpdateDedupeRules::semanticDuplicateKeys($payload);
            $existing = $keys === []
                ? null
                : CountryUpdate::query()
                    ->where('country_id', $country->id)
                    ->where('review_status', '!=', 'rejected')
                    ->get()
                    ->first(fn (CountryUpdate $update) => array_intersect($keys, CountryUpdateDedupeRules::semanticDuplicateKeys($update)) !== []);
        }

        if ($existing) {
            return ['status' => 'duplicate', 'country_update_id' => $existing->id];
        }

        $update = CountryUpdate::query()->create($payload + ['review_status' => 'unreviewed']);

        return ['status' => 'captured', 'country_update_id' => $update->id];
    }

    private function parseResultDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function settingString(string $key, mixed $default = ''): string
    {
        $value = CrawlerSetting::query()->where('setting_key', $key)->value('setting_value');
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : (string) $default;
    }

    private function settingBoolean(string $key, mixed $default = false): bool
    {
        $value = CrawlerSetting::query()->where('setting_key', $key)->value('setting_value');
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return (bool) $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
