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
                    $summary['results']++;
                    $sourceUrl = (string) ($result['link'] ?? '');
                    $title = trim((string) ($result['title'] ?? ''));
                    $sourceName = trim((string) ($result['source'] ?? parse_url($sourceUrl, PHP_URL_HOST) ?: 'SerpAPI result'));
                    $snippet = trim((string) ($result['snippet'] ?? ''));
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
