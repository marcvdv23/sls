<?php

namespace App\Services;

use App\Models\Country;
use App\Models\IntelligenceKeyword;
use App\Models\IntelligenceSource;
use App\Models\IntelligenceSourceAudit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class SerpApiSourceDiscoveryService
{
    /**
     * @return array{countries:int, queries:int, candidates:int, created:int, updated:int, errors:array<int, string>, items:array<int, array<string, mixed>>}
     */
    public function discover(
        string $region = 'Africa',
        int $countryLimit = 0,
        int $queriesPerCountry = 4,
        int $resultsPerQuery = 8,
        bool $dryRun = false,
    ): array {
        $apiKey = (string) env('SERPAPI_KEY', '');
        $errors = [];
        $items = [];
        $queries = 0;
        $created = 0;
        $updated = 0;
        $candidates = 0;

        $countryLimit = $countryLimit > 0 ? $countryLimit : (int) env('SERPAPI_PILOT_LIMIT', 0);

        $countries = Country::query()
            ->where('region', $region)
            ->whereNotIn('iso_code', ['CN', 'RU', 'KP', 'IR'])
            ->orderBy('name')
            ->when($countryLimit > 0, fn ($query) => $query->limit($countryLimit))
            ->get();

        foreach ($countries as $country) {
            $countryQueries = $this->queriesForCountry($country)
                ->take(max(1, $queriesPerCountry));

            foreach ($countryQueries as $queryText) {
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
                    $this->audit(null, $queryText, $requestUrl, null, false, 0, '', $exception->getMessage());
                    continue;
                }

                $body = (string) $response->body();
                $data = $response->json();
                $organicResults = collect(is_array($data) ? ($data['organic_results'] ?? []) : []);

                $queryCandidates = $organicResults
                    ->take(max(1, $resultsPerQuery))
                    ->map(fn (array $result) => $this->candidateFromResult($country, $result, $queryText))
                    ->filter()
                    ->values();

                $this->audit(null, $queryText, $requestUrl, $response->status(), $response->ok(), $queryCandidates->count(), $body, null);

                foreach ($queryCandidates as $candidate) {
                    $candidates++;
                    $items[] = $candidate;

                    if ($dryRun) {
                        continue;
                    }

                    $source = IntelligenceSource::updateOrCreate([
                        'country_iso' => $candidate['country_iso'],
                        'domain' => $candidate['domain'],
                        'url' => $candidate['url'],
                    ], [
                        'region' => $candidate['region'],
                        'name' => $candidate['name'],
                        'source_class' => $candidate['source_class'],
                        'focus' => $candidate['focus'],
                        'access_method' => 'serpapi_discovery_candidate',
                        'connector' => 'direct_site_monitor',
                        'registration_status' => 'needs_review',
                        'registration_notes' => $candidate['notes'],
                        'is_enabled' => false,
                    ]);

                    $source->wasRecentlyCreated ? $created++ : $updated++;
                }
            }
        }

        return [
            'countries' => $countries->count(),
            'queries' => $queries,
            'candidates' => $candidates,
            'created' => $created,
            'updated' => $updated,
            'errors' => array_values(array_unique($errors)),
            'items' => $items,
        ];
    }

    private function queriesForCountry(Country $country): Collection
    {
        $name = $country->name;
        $language = Str::lower((string) $country->default_language_code);

        return $this->sourceDiscoveryTerms($language)
            ->map(fn (string $term) => '"' . $name . '" "' . $term . '"')
            ->unique()
            ->values();
    }

    private function sourceDiscoveryTerms(string $language): Collection
    {
        $languages = collect([$language, 'en'])
            ->filter()
            ->unique()
            ->values();

        if (Schema::hasTable('intelligence_keywords')) {
            $managedTerms = IntelligenceKeyword::query()
                ->where('is_enabled', true)
                ->where('focus', 'source_discovery')
                ->whereIn('language_code', $languages->all())
                ->orderByRaw("FIELD(category, 'social_security_admin', 'labour_ministry', 'civil_service', 'procurement_portal', 'core', 'custom')")
                ->orderBy('term')
                ->pluck('term')
                ->filter()
                ->values();

            if ($managedTerms->isNotEmpty()) {
                return $managedTerms;
            }
        }

        return $languages
            ->flatMap(fn (string $code) => config("country_intelligence.source_discovery_terms.$code", []))
            ->filter()
            ->unique()
            ->values();
    }

    private function candidateFromResult(Country $country, array $result, string $queryText): ?array
    {
        $url = trim((string) ($result['link'] ?? ''));
        $title = $this->cleanTitle((string) ($result['title'] ?? ''));
        $snippet = trim((string) ($result['snippet'] ?? ''));
        $domain = $this->domainFromUrl($url);

        if ($url === '' || $domain === '' || $title === '') {
            return null;
        }

        if ($this->isBlockedDiscoveryDomain($domain)) {
            return null;
        }

        $haystack = Str::lower($title . ' ' . $snippet . ' ' . $domain . ' ' . $url);

        if (! Str::contains($haystack, [
            'social security',
            'social insurance',
            'national insurance',
            'pension',
            'provident',
            'retirement',
            'securite sociale',
            'prevoyance',
            'retraite',
            'seguranca social',
            'previdencia',
            'pensoes',
            'الضمان',
            'التأمينات',
            'التقاعد',
            'المعاشات',
            'nssf',
            'cnss',
            'inss',
            'ministry of labour',
            'ministry of labor',
            'ministry of social security',
            'ministry of social protection',
            'ministry of public service',
            'civil service',
            'public service commission',
            'fonction publique',
            'ministere du travail',
            'ministerio do trabalho',
            'funcao publica',
            'وزارة العمل',
            'الخدمة المدنية',
            'procurement portal',
            'public procurement',
            'government procurement',
            'marches publics',
            'contratacao publica',
            'المشتريات الحكومية',
        ])) {
            return null;
        }

        $sourceClass = $this->sourceClass($haystack);
        $confidence = $this->confidence($country, $domain, $haystack);

        if ($confidence < 5) {
            return null;
        }

        return [
            'country_iso' => $country->iso_code,
            'country' => $country->name,
            'region' => $country->region,
            'name' => Str::limit($title, 255, ''),
            'domain' => $domain,
            'url' => $url,
            'source_class' => $sourceClass,
            'focus' => $sourceClass === 'central_tender_portal' ? 'tenders' : 'news',
            'confidence' => $confidence,
            'query' => $queryText,
            'notes' => 'Discovered by SerpAPI. Confidence ' . $confidence . '. Query: ' . $queryText . '. Snippet: ' . Str::limit($snippet, 350, ''),
        ];
    }

    private function sourceClass(string $haystack): string
    {
        return match (true) {
            Str::contains($haystack, ['procurement', 'tender', 'rfp', 'bids', 'contracting', 'marches publics', 'contratacao publica', 'المشتريات']) => 'central_tender_portal',
            Str::contains($haystack, ['civil service', 'public service commission', 'ministry of public service', 'fonction publique', 'funcao publica', 'الخدمة المدنية']) => 'civil_service',
            Str::contains($haystack, ['ministry', 'labour', 'labor', 'government', 'ministere', 'ministerio', 'وزارة']) => 'government',
            default => 'social_security_admin',
        };
    }

    private function confidence(Country $country, string $domain, string $haystack): int
    {
        $score = 0;
        $iso = Str::lower((string) $country->iso_code);

        if (Str::contains($domain, ['.gov', '.gob', '.go.', '.gouv', '.govt'])) {
            $score += 3;
        }

        if (Str::endsWith($domain, '.' . $iso)) {
            $score += 2;
        }

        if (Str::contains($haystack, ['official', 'authority', 'administration', 'fund', 'caisse', 'instituto', 'ministry', 'government'])) {
            $score += 2;
        }

        if (Str::contains($haystack, Str::lower($country->name))) {
            $score += 1;
        }

        if (Str::contains($haystack, ['facebook.com', 'linkedin.com', 'wikipedia.org', 'youtube.com', 'x.com', 'twitter.com'])) {
            $score -= 4;
        }

        return $score;
    }

    private function isBlockedDiscoveryDomain(string $domain): bool
    {
        return Str::contains($domain, [
            'facebook.com',
            'instagram.com',
            'linkedin.com',
            'x.com',
            'twitter.com',
            'youtube.com',
            'apps.apple.com',
            'play.google.com',
            'wikipedia.org',
            'researchgate.net',
            'swfinstitute.org',
            'freshdi.com',
            'lepetitjournal.com',
            'sikafinance.com',
            'zoomalgerie.com',
            'webapps.ilo.org',
        ]);
    }

    private function domainFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return Str::lower((string) preg_replace('/^www\./i', '', (string) $host));
    }

    private function cleanTitle(string $title): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5)));
    }

    private function audit(?IntelligenceSource $source, string $query, string $url, ?int $status, bool $ok, int $items, string $response, ?string $error): void
    {
        IntelligenceSourceAudit::query()->create([
            'intelligence_source_id' => $source?->id,
            'source_name' => 'SerpAPI Source Discovery',
            'domain' => 'serpapi.com',
            'focus' => 'source_discovery',
            'method' => 'GET',
            'request_url' => $url,
            'request_payload' => json_encode(['query' => $query]),
            'http_status' => $status,
            'ok' => $ok,
            'items_found' => $items,
            'response_excerpt' => Str::limit($response, 5000, ''),
            'error_message' => $error,
            'checked_at' => now(),
        ]);
    }

    private function httpOptions(): array
    {
        $verifySsl = filter_var(env('SERPAPI_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);

        if (! $verifySsl) {
            return ['verify' => false];
        }

        $caBundle = trim((string) env('SERPAPI_CA_BUNDLE', ''));

        if ($caBundle !== '' && is_file($caBundle)) {
            return ['verify' => $caBundle];
        }

        return [];
    }
}
