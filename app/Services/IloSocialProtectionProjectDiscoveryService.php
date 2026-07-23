<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryTopic;
use App\Models\CountryUpdate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class IloSocialProtectionProjectDiscoveryService
{
    private const DOMAIN = 'social-protection.org';
    private const SOURCE_NAME = 'ILO Social Protection Platform';

    /**
     * @return array{countries:int, queries:int, found:int, stored:int, errors:array<int, string>, items:array<int, array<string, mixed>>}
     */
    public function discover(
        array $countryKeys = [],
        ?string $region = null,
        int $countryLimit = 0,
        int $queriesPerCountry = 3,
        int $resultsPerQuery = 5,
        bool $dryRun = false,
    ): array {
        $apiKey = trim((string) env('SERPAPI_KEY', ''));
        $errors = [];
        $items = [];
        $queries = 0;
        $found = 0;
        $stored = 0;
        $seenUrls = [];

        $countries = $this->countries($countryKeys, $region, $countryLimit);

        foreach ($countries as $country) {
            $startedAt = now();
            $storedForCountry = 0;

            foreach ($this->queriesForCountry($country)->take(max(1, $queriesPerCountry)) as $queryText) {
                $queries++;

                if ($apiKey === '') {
                    $errors[] = 'SERPAPI_KEY is not configured. Example query not sent: ' . $queryText;
                    continue;
                }

                $requestUrl = 'https://serpapi.com/search.json?' . http_build_query([
                    'engine' => 'google',
                    'q' => $queryText,
                    'num' => max(1, min(10, $resultsPerQuery)),
                    'api_key' => $apiKey,
                ]);

                try {
                    $response = Http::timeout(30)
                        ->retry(1, 500)
                        ->acceptJson()
                        ->withOptions($this->httpOptions())
                        ->get($requestUrl);
                } catch (Throwable $exception) {
                    $errors[] = Str::limit($country->iso_code . ' ' . $queryText . ': ' . $exception->getMessage(), 500, '');
                    continue;
                }

                if (! $response->ok()) {
                    $errors[] = Str::limit($country->iso_code . ' ' . $queryText . ': HTTP ' . $response->status(), 500, '');
                    continue;
                }

                $results = collect($response->json('organic_results', []))
                    ->map(fn (array $result) => $this->candidateFromResult($country, $result, $queryText))
                    ->filter()
                    ->unique('source_url')
                    ->values();

                foreach ($results as $item) {
                    $urlKey = Str::lower((string) $item['source_url']);

                    if (isset($seenUrls[$urlKey])) {
                        continue;
                    }

                    $seenUrls[$urlKey] = true;
                    $found++;
                    $items[] = $item;

                    if ($dryRun) {
                        continue;
                    }

                    $topic = $this->ensureTopic($country);
                    CountryUpdate::query()->updateOrCreate(
                        [
                            'country_id' => $country->id,
                            'source_url' => $item['source_url'],
                        ],
                        [
                            'country_topic_id' => $topic->id,
                            'title' => $item['title'],
                            'title_english' => $item['title'],
                            'title_original' => $item['title'],
                            'source_name' => self::SOURCE_NAME,
                            'publication_date' => now()->toDateString(),
                            'retrieved_at' => now(),
                            'summary' => $item['summary'],
                            'relevance_score' => $item['relevance_score'],
                            'review_status' => 'unreviewed',
                        ],
                    );

                    $stored++;
                    $storedForCountry++;
                }
            }

            if (! $dryRun) {
                CountryMonitorRun::query()->create([
                    'country_id' => $country->id,
                    'focus' => 'social_security',
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                    'sources_checked' => [self::DOMAIN],
                    'items_found' => $storedForCountry,
                    'status' => 'completed',
                ]);
            }
        }

        return [
            'countries' => $countries->count(),
            'queries' => $queries,
            'found' => $found,
            'stored' => $stored,
            'errors' => array_values(array_unique($errors)),
            'items' => $items,
        ];
    }

    private function countries(array $countryKeys, ?string $region, int $countryLimit): Collection
    {
        $excludedIsoCodes = ['CN', 'CU', 'IR', 'KP', 'RU', 'SY'];

        $countries = Country::query()
            ->whereNotIn('iso_code', $excludedIsoCodes)
            ->when($region, fn ($query) => $query->where('region', $region))
            ->orderBy('name')
            ->get();

        $keys = collect($countryKeys)
            ->map(fn ($key) => Str::lower(trim((string) $key)))
            ->filter()
            ->values();

        if ($keys->isNotEmpty()) {
            $countries = $countries->filter(fn (Country $country) => $keys->contains(Str::lower((string) $country->iso_code))
                || $keys->contains(Str::lower((string) $country->name)));
        }

        return $countryLimit > 0 ? $countries->take($countryLimit)->values() : $countries->values();
    }

    private function queriesForCountry(Country $country): Collection
    {
        $name = $country->name;

        return collect([
            '"' . $name . '" site:social-protection.org/gimi/Contribution.action "social protection"',
            '"' . $name . '" site:social-protection.org/gimi/Contribution.action "social security"',
            '"' . $name . '" site:social-protection.org/gimi/Contribution.action pension',
            '"' . $name . '" site:social-protection.org/gimi/Contribution.action digitalization',
            '"' . $name . '" site:social-protection.org/gimi/Contribution.action "digital transformation"',
            '"' . $name . '" site:social-protection.org/gimi/Contribution.action "social registry"',
        ]);
    }

    private function candidateFromResult(Country $country, array $result, string $queryText): ?array
    {
        $url = trim((string) ($result['link'] ?? ''));
        $canonicalUrl = $this->canonicalContributionUrl($url);
        $title = $this->plainText((string) ($result['title'] ?? ''));
        $snippet = $this->plainText((string) ($result['snippet'] ?? ''));
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        if ($canonicalUrl === '' || $title === '' || ! Str::contains($host, self::DOMAIN)) {
            return null;
        }

        if (Str::lower($title) === 'ilo | social protection' && $snippet === '') {
            return null;
        }

        $haystack = Str::lower($title . ' ' . $snippet . ' ' . $canonicalUrl . ' ' . $queryText);
        $digitalSignal = Str::contains($haystack, ['digitalization', 'digitization', 'digital transformation', 'social registry', 'beneficiary registry', 'management information system', 'payment system']);

        return [
            'country_iso' => $country->iso_code,
            'country' => $country->name,
            'title' => Str::limit($title, 500, ''),
            'summary' => Str::limit($snippet !== '' ? $snippet : 'ILO Social Protection project page discovered by SerpAPI indexed search.', 2000, ''),
            'source_url' => Str::limit($canonicalUrl, 1000, ''),
            'query' => $queryText,
            'relevance_score' => $digitalSignal ? 9.0 : 7.0,
        ];
    }

    private function canonicalContributionUrl(string $url): string
    {
        if ($url === '' || ! Str::contains($url, 'Contribution.action')) {
            return '';
        }

        if (! preg_match('/[?&]id=(\d+)/', $url, $matches)) {
            return '';
        }

        return 'https://www.social-protection.org/gimi/Contribution.action?id=' . $matches[1];
    }

    private function ensureTopic(Country $country): CountryTopic
    {
        return CountryTopic::query()->updateOrCreate(
            ['country_id' => $country->id, 'topic' => 'social security and pension intelligence'],
            ['tracking_status' => 'active'],
        );
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function httpOptions(): array
    {
        $options = [];
        $verifySsl = filter_var(env('SERPAPI_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);

        if (! $verifySsl) {
            $options['verify'] = false;
        }

        $caBundle = trim((string) env('SERPAPI_CA_BUNDLE', ''));

        if ($verifySsl && $caBundle !== '') {
            $options['verify'] = $caBundle;
        }

        return $options;
    }
}
