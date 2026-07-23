<?php

namespace App\Services;

use App\Models\Country;
use App\Models\MarketCrawler;
use App\Models\MarketCrawlerRun;
use App\Models\MarketOrganization;
use App\Models\MarketOrganizationContact;
use App\Models\MarketOrganizationTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class UniversityMarketCrawlerService
{
    /**
     * @param array<int, int> $organizationIds
     * @return array{organizations:int, queries:int, urls_updated:int, errors:array<int, string>}
     */
    public function runDiscoveryPilot(MarketCrawler $crawler, array|string|null $country = null, int $organizationLimit = 10, int $maxCalls = 25, int $resultsPerQuery = 5, array $organizationIds = []): array
    {
        $apiKey = trim((string) env('SERPAPI_KEY', ''));
        $queries = 0;
        $urlsUpdated = 0;
        $errors = [];

        $organizations = $this->discoveryOrganizationQuery($crawler, $country, $organizationIds)
            ->limit(max(1, $organizationLimit))
            ->get();

        foreach ($organizations as $organization) {
            foreach ($this->discoveryQueries($organization) as $query) {
                if ($queries >= max(1, $maxCalls)) {
                    break 2;
                }

                $queries++;
                $startedAt = now();
                $requestUrl = 'https://serpapi.com/search.json?' . http_build_query([
                    'engine' => 'google',
                    'q' => $query,
                    'num' => max(1, min(10, $resultsPerQuery)),
                    'api_key' => $apiKey ?: 'missing',
                ]);

                if ($apiKey === '') {
                    $errors[] = 'SERPAPI_KEY is not configured. Query not sent: ' . $query;
                    $this->recordRun($crawler, $organization, 'serpapi_discovery', 'error', $startedAt, [
                        'query_text' => $query,
                        'request_url' => $requestUrl,
                        'error_message' => 'SERPAPI_KEY is not configured.',
                    ]);
                    continue;
                }

                try {
                    $response = Http::timeout(30)
                        ->retry(1, 500)
                        ->acceptJson()
                        ->withOptions($this->httpOptions())
                        ->get($requestUrl);
                } catch (Throwable $exception) {
                    $errors[] = Str::limit($organization->name . ': ' . $exception->getMessage(), 500, '');
                    $this->recordRun($crawler, $organization, 'serpapi_discovery', 'error', $startedAt, [
                        'query_text' => $query,
                        'request_url' => $requestUrl,
                        'error_message' => $exception->getMessage(),
                    ]);
                    continue;
                }

                $candidates = collect((array) ($response->json('organic_results') ?? []))
                    ->take(max(1, $resultsPerQuery))
                    ->map(fn (array $result) => $this->candidateFromSerpResult($organization, $result))
                    ->filter()
                    ->values();

                $updatedFields = $this->applyBestCandidates($organization, $candidates);
                $organization->update(['market_crawler_id' => $crawler->id]);
                $urlsUpdated += count($updatedFields);

                $this->recordRun($crawler, $organization, 'serpapi_discovery', $response->ok() ? 'completed' : 'error', $startedAt, [
                    'query_text' => $query,
                    'request_url' => $requestUrl,
                    'http_status' => $response->status(),
                    'items_found' => $candidates->count(),
                    'urls_updated' => count($updatedFields),
                    'result_payload' => [
                        'updated_fields' => $updatedFields,
                        'candidates' => $candidates->take(5)->values()->all(),
                    ],
                    'response_excerpt' => Str::limit((string) $response->body(), 5000, ''),
                    'error_message' => $response->ok() ? null : Str::limit((string) $response->body(), 1000, ''),
                ]);
            }
        }

        $crawler->update([
            'last_run_at' => now(),
            'last_error' => $errors === [] ? null : implode(' | ', array_slice($errors, 0, 3)),
        ]);

        return [
            'organizations' => $organizations->count(),
            'queries' => $queries,
            'urls_updated' => $urlsUpdated,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return Collection<int, MarketOrganization>
     */
    public function previewDiscoveryOrganizations(MarketCrawler $crawler, array|string|null $country = null, int $organizationLimit = 10): Collection
    {
        return $this->discoveryOrganizationQuery($crawler, $country)
            ->limit(max(1, $organizationLimit))
            ->get();
    }

    /**
     * @return array{organizations:int, pages_checked:int, urls_updated:int, contacts_found:int, errors:array<int, string>}
     */
    public function runCrawlPilot(MarketCrawler $crawler, array|string|null $country = null, int $organizationLimit = 10, int $pageLimit = 6, array $organizationIds = []): array
    {
        $pagesChecked = 0;
        $urlsUpdated = 0;
        $contactsFound = 0;
        $errors = [];

        $organizations = $this->crawlOrganizationQuery($crawler, $country, $organizationIds)
            ->limit(max(1, $organizationLimit))
            ->get();

        foreach ($organizations as $index => $organization) {
            $startedAt = now();
            $organizationContactsFound = 0;
            $organizationPagesChecked = 0;
            $organizationUrlsUpdated = 0;
            $pageErrors = [];

            $checkedUrls = [];

            foreach ($this->crawlUrls($organization)->take(max(1, $pageLimit)) as $page) {
                $url = $page['url'];
                $pageType = $page['type'];
                $checkedUrls[] = $url;

                try {
                    $response = Http::timeout(15)
                        ->connectTimeout(5)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 1G-SLS Organization Crawler'])
                        ->withOptions($this->httpOptions())
                        ->get($url);
                } catch (Throwable $exception) {
                    $pageErrors[] = Str::limit($url . ': ' . $exception->getMessage(), 500, '');
                    continue;
                }

                if (! $response->ok()) {
                    $pageErrors[] = Str::limit($url . ': HTTP ' . $response->status(), 500, '');
                    continue;
                }

                $organizationPagesChecked++;
                $pagesChecked++;

                $text = $this->plainText((string) $response->body());
                $linkCandidates = $this->candidatesFromPageLinks($organization, $url, (string) $response->body());
                $updatedFields = $this->applyBestCandidates($organization, $linkCandidates);

                if ($updatedFields !== []) {
                    $organizationUrlsUpdated += count($updatedFields);
                    $urlsUpdated += count($updatedFields);
                    $organization->refresh();
                }

                $contacts = $this->contactsFromText($organization, $crawler, $url, $pageType, $text);

                foreach ($contacts as $contactData) {
                    MarketOrganizationContact::updateOrCreate(
                        ['source_fingerprint' => $contactData['source_fingerprint']],
                        $contactData,
                    );
                    $organizationContactsFound++;
                    $contactsFound++;
                }
            }

            $organization->update([
                'market_crawler_id' => $crawler->id,
                'last_crawled_at' => now(),
                'last_crawler_name' => $crawler->name,
                'next_crawl_at' => $pageErrors === [] ? now()->addWeek() : now()->addDay(),
                'last_error' => $pageErrors === [] ? null : implode(' | ', array_slice($pageErrors, 0, 3)),
            ]);

            if ($pageErrors !== []) {
                $this->createCrawlerFollowUpTask($organization, $crawler, $pageErrors);
            } else {
                $this->closeCrawlerFollowUpTasks($organization);
            }

            $this->recordRun($crawler, $organization, 'direct_page_crawl', $pageErrors === [] ? 'completed' : 'warning', $startedAt, [
                'items_found' => $organizationPagesChecked,
                'urls_updated' => $organizationUrlsUpdated,
                'contacts_found' => $organizationContactsFound,
                'result_payload' => [
                    'pages_checked' => $organizationPagesChecked,
                    'urls_updated' => $organizationUrlsUpdated,
                    'checked_urls' => $checkedUrls,
                    'errors' => $pageErrors,
                ],
                'error_message' => $pageErrors === [] ? null : implode(' | ', array_slice($pageErrors, 0, 3)),
            ]);

            $errors = array_merge($errors, $pageErrors);

            if (($index + 1) % 50 === 0 && ($index + 1) < $organizations->count()) {
                $this->recordRun($crawler, null, 'direct_crawl_throttle_pause', 'completed', now(), [
                    'items_found' => $index + 1,
                    'result_payload' => [
                        'organizations_processed' => $index + 1,
                        'pause_seconds' => 120,
                        'reason' => 'Polite crawl throttle after 50 organizations.',
                    ],
                    'response_excerpt' => 'Paused for 120 seconds after processing ' . ($index + 1) . ' organizations.',
                ]);

                sleep(120);
            }
        }

        $crawler->update([
            'last_run_at' => now(),
            'last_error' => $errors === [] ? null : implode(' | ', array_slice($errors, 0, 3)),
        ]);

        return [
            'organizations' => $organizations->count(),
            'pages_checked' => $pagesChecked,
            'urls_updated' => $urlsUpdated,
            'contacts_found' => $contactsFound,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return Collection<int, MarketOrganization>
     */
    public function previewCrawlOrganizations(MarketCrawler $crawler, array|string|null $country = null, int $organizationLimit = 10): Collection
    {
        return $this->crawlOrganizationQuery($crawler, $country)
            ->limit(max(1, $organizationLimit))
            ->get();
    }

    /**
     * Revisit original source pages for contacts whose email was captured but whose name still needs research.
     *
     * @return array{scanned:int,pages_checked:int,resolved:int,errors:array<int,string>}
     */
    public function resolveContactNamesFromSourcePages(int $limit = 200): array
    {
        $scanned = 0;
        $pagesChecked = 0;
        $resolved = 0;
        $errors = [];
        $pageCache = [];

        MarketOrganizationContact::query()
            ->with(['organization', 'crawler'])
            ->where('verification_status', 'needs_name_research')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereNotNull('source_url')
            ->where('source_url', '<>', '')
            ->orderByDesc('updated_at')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (MarketOrganizationContact $contact) use (&$scanned, &$pagesChecked, &$resolved, &$errors, &$pageCache) {
                $scanned++;
                $url = $this->normalizeCrawlUrl((string) $contact->source_url);

                if ($url === '') {
                    return;
                }

                if (! array_key_exists($url, $pageCache)) {
                    try {
                        $response = Http::timeout(20)
                            ->connectTimeout(6)
                            ->withHeaders(['User-Agent' => 'Mozilla/5.0 1G-SLS Contact Resolver'])
                            ->withOptions($this->httpOptions())
                            ->get($url);
                    } catch (Throwable $exception) {
                        $errors[] = Str::limit($url . ': ' . $exception->getMessage(), 500, '');
                        $pageCache[$url] = null;

                        return;
                    }

                    if (! $response->ok()) {
                        $errors[] = Str::limit($url . ': HTTP ' . $response->status(), 500, '');
                        $pageCache[$url] = null;

                        return;
                    }

                    $pagesChecked++;
                    $pageCache[$url] = $this->plainText((string) $response->body());
                }

                $text = $pageCache[$url];

                if (! is_string($text) || $text === '') {
                    return;
                }

                $context = $this->cleanText($this->emailContext($text, (string) $contact->email, 900));
                [$personName, $jobTitle] = $this->personAndTitleFromContext($context);
                [$personName, $jobTitle, $status] = $this->normalizeCapturedContactIdentity(
                    $personName,
                    $jobTitle ?: $contact->job_title,
                    (string) $contact->email
                );

                if (! $personName || $personName === $jobTitle || $this->looksInvalidPersonName($personName)) {
                    if ($jobTitle && $jobTitle !== $contact->job_title) {
                        $contact->update([
                            'job_title' => $jobTitle,
                            'context_excerpt' => $context ?: $contact->context_excerpt,
                        ]);
                    }

                    return;
                }

                $contact->update([
                    'person_name' => $personName,
                    'job_title' => $jobTitle,
                    'context_excerpt' => $context ?: $contact->context_excerpt,
                    'verification_status' => $status === 'needs_name_research' ? 'published' : $status,
                    'notes' => trim((string) $contact->notes . "\nName resolved by revisiting original source page."),
                ]);

                $resolved++;
            });

        return [
            'scanned' => $scanned,
            'pages_checked' => $pagesChecked,
            'resolved' => $resolved,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public function profile(MarketCrawler $crawler): array
    {
        return match ($crawler->crawler_key) {
            'university_procurement_leadership' => [
                'singular' => 'university',
                'plural' => 'universities',
                'organization_type' => 'university',
                'industry' => 'higher_education',
                'subcategory' => null,
                'description' => 'SerpAPI finds official university websites; the crawler then checks procurement/tenders, leadership/administration, media/press, HR, IT, and published email contacts.',
            ],
            'social_security_organization' => [
                'singular' => 'social security organization',
                'plural' => 'social security organizations',
                'organization_type' => 'government_agency',
                'industry' => 'government',
                'subcategory' => 'social_security_administration',
                'description' => 'SerpAPI finds official organization websites; the crawler then checks media/press/announcements, procurement/tenders, leadership, and published email contacts.',
            ],
            'school_district_procurement_leadership' => [
                'singular' => 'school district',
                'plural' => 'school districts',
                'organization_type' => 'school_district',
                'industry' => 'k12_education',
                'subcategory' => 'school_district',
                'description' => 'SerpAPI finds official district websites; the crawler then checks procurement/tenders, superintendent/leadership, HR, finance, IT, news, and published public contacts.',
            ],
            'utility_procurement_leadership' => [
                'singular' => 'utility organization',
                'plural' => 'utility organizations',
                'organization_type' => 'utility',
                'industry' => 'utilities',
                'subcategory' => null,
                'description' => 'SerpAPI finds official utility websites; the crawler then checks procurement/supplier pages, leadership, HR, IT, news, and published contacts.',
            ],
            'oil_gas_procurement_leadership' => [
                'singular' => 'oil and gas organization',
                'plural' => 'oil and gas organizations',
                'organization_type' => 'oil_gas_company',
                'industry' => 'oil_gas',
                'subcategory' => null,
                'description' => 'SerpAPI finds official oil and gas company websites; the crawler then checks procurement/supplier pages, leadership, HR, IT, news, and published contacts.',
            ],
            'bank_domain_enrichment' => [
                'singular' => 'bank',
                'plural' => 'banks',
                'organization_type' => 'financial_institution',
                'industry' => 'banking_finance',
                'subcategory' => null,
                'description' => 'Guesses and verifies official bank domains first; accepted bank websites can then be crawled for media/press/announcements, procurement, leadership, HR, IT, and public contacts.',
            ],
            'national_procurement_portal' => [
                'singular' => 'procurement portal',
                'plural' => 'procurement portals',
                'organization_type' => 'procurement_portal',
                'industry' => 'procurement',
                'subcategory' => 'procurement_portal',
                'description' => 'SerpAPI finds official procurement portal websites; the crawler then checks tender listings, registration/access information, help pages, news, and public contacts.',
            ],
            'competitor_bidder_enrichment' => [
                'singular' => 'competitor/bidder organization',
                'plural' => 'competitor/bidder organizations',
                'organization_type' => 'private_company',
                'industry' => 'other',
                'subcategory' => 'other',
                'description' => 'Crawls competitor and bidder organizations created from tender documents to confirm websites, supplier/procurement pages, leadership, HR, IT, news, and public contacts.',
            ],
            default => [
                'singular' => 'organization',
                'plural' => 'organizations',
                'organization_type' => $this->organizationTypeForCrawler($crawler),
                'industry' => $this->industryForCrawler($crawler),
                'subcategory' => null,
                'description' => 'SerpAPI finds missing main websites; the crawler then checks procurement, leadership, media/press, HR, IT, and published email contacts.',
            ],
        };
    }

    private function organizationTypeForCrawler(MarketCrawler $crawler): string
    {
        return match ($crawler->crawler_type) {
            'university_contact_crawler' => 'university',
            'school_district_contact_crawler' => 'school_district',
            'utility_contact_crawler' => 'utility',
            'oil_gas_contact_crawler' => 'oil_gas_company',
            'national_procurement_crawler' => 'procurement_portal',
            'social_security_contact_crawler' => 'government_agency',
            default => 'other',
        };
    }

    private function industryForCrawler(MarketCrawler $crawler): ?string
    {
        return match ($crawler->crawler_type) {
            'university_contact_crawler' => 'higher_education',
            'school_district_contact_crawler' => 'k12_education',
            'utility_contact_crawler' => 'utilities',
            'oil_gas_contact_crawler' => 'oil_gas',
            'national_procurement_crawler' => 'procurement',
            'social_security_contact_crawler' => 'government',
            default => null,
        };
    }

    private function organizationQuery(MarketCrawler $crawler, array|string|null $country)
    {
        $countries = $this->normalizeCountryInputs($country);
        $profile = $this->profile($crawler);

        return MarketOrganization::query()
            ->where('organization_type', $profile['organization_type'])
            ->when($profile['industry'], fn ($query, $industry) => $query->where('industry', $industry))
            ->when($profile['subcategory'], fn ($query, $subcategory) => $query->where('organization_subcategory', $subcategory))
            ->whereNotIn('country_iso', $this->sanctionedCountryIsos())
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', $this->excludedOrganizationStatuses());
            })
            ->when($countries !== [], fn ($query) => $query->where(function ($inner) use ($countries) {
                foreach ($countries as $countryInput) {
                    $countryIso = $this->countryIsoFromInput($countryInput);
                    $isIsoInput = strlen($countryInput) === 2;

                    $inner->orWhere('country_iso', Str::upper($countryInput));

                    if (! $isIsoInput) {
                        $inner->orWhere('country', 'like', '%' . $countryInput . '%');
                    }

                    if ($countryIso !== null && $countryIso !== Str::upper($countryInput)) {
                        $inner->orWhere('country_iso', $countryIso)
                            ->orWhere('country', $countryIso);
                    }
                }
            }));
    }

    private function sanctionedCountryIsos(): array
    {
        return ['CU', 'IR', 'KP'];
    }

    private function excludedOrganizationStatuses(): array
    {
        return ['sanctioned', 'duplicate'];
    }

    /**
     * @param array<int, int> $organizationIds
     */
    private function discoveryOrganizationQuery(MarketCrawler $crawler, array|string|null $country, array $organizationIds = [])
    {
        $organizationIds = array_values(array_unique(array_filter(array_map('intval', $organizationIds))));

        return $this->organizationQuery($crawler, $country)
            ->where(function ($query) {
                $query->whereNull('website_url')
                    ->orWhere('website_url', '');
            })
            ->when($organizationIds !== [], fn ($query) => $query->whereIn('id', $organizationIds))
            ->orderBy('country')
            ->orderBy('name');
    }

    /**
     * @param array<int, int> $organizationIds
     */
    private function crawlOrganizationQuery(MarketCrawler $crawler, array|string|null $country, array $organizationIds = [])
    {
        $organizationIds = array_values(array_unique(array_filter(array_map('intval', $organizationIds))));

        return $this->organizationQuery($crawler, $country)
            ->where(function ($query) {
                $query->whereNotNull('website_url')
                    ->orWhereNotNull('procurement_page_url')
                    ->orWhereNotNull('leadership_page_url')
                    ->orWhereNotNull('hr_page_url')
                    ->orWhereNotNull('it_page_url')
                    ->orWhereNotNull('news_page_url');
            })
            ->when($organizationIds !== [], fn ($query) => $query->whereIn('id', $organizationIds))
            ->orderByRaw('last_crawled_at IS NULL DESC')
            ->orderBy('last_crawled_at')
            ->orderBy('country')
            ->orderBy('name');
    }

    /**
     * @return array<int, string>
     */
    private function normalizeCountryInputs(array|string|null $country): array
    {
        if (is_array($country)) {
            $values = $country;
        } else {
            $values = explode(',', (string) $country);
        }

        return collect($values)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function countryIsoFromInput(string $country): ?string
    {
        if ($country === '') {
            return null;
        }

        if (strlen($country) === 2) {
            return Str::upper($country);
        }

        $match = Country::query()
            ->where('name', 'like', '%' . $country . '%')
            ->orWhere('iso_code', Str::upper($country))
            ->first();

        return $match ? Str::upper((string) $match->iso_code) : null;
    }

    public function previewDiscoveryQuery(MarketOrganization $organization): string
    {
        return (string) ($this->discoveryQueries($organization)->first() ?? '');
    }

    private function discoveryQueries(MarketOrganization $organization): Collection
    {
        $name = $organization->name;
        $country = trim((string) ($organization->country ?: $organization->country_iso));
        $countryRecord = $organization->country_iso
            ? Country::query()->where('iso_code', Str::upper((string) $organization->country_iso))->first()
            : null;
        $language = Str::lower((string) ($countryRecord?->default_language_code ?: 'en'));
        $officialWebsiteTerm = $this->officialWebsiteTerm($language);

        return collect([
            $organization->website_url ? null : '"' . $name . '" "' . $country . '" ' . $officialWebsiteTerm,
        ])->filter()->values();
    }

    private function officialWebsiteTerm(string $language): string
    {
        return match (Str::lower($language)) {
            'fr' => 'site officiel',
            'pt' => 'site oficial',
            'es' => 'sitio oficial',
            'ar' => 'الموقع الرسمي',
            default => 'official website',
        };
    }

    /**
     * @return array{procurement: Collection<int, string>, leadership: Collection<int, string>, news: Collection<int, string>, hr_it: Collection<int, string>}
     */
    private function localizedTerms(string $countryIso): array
    {
        $country = Country::query()->where('iso_code', Str::upper($countryIso))->first();
        $language = Str::lower((string) ($country?->default_language_code ?: 'en'));
        $terms = [
            'en' => [
                'procurement' => ['procurement', 'tenders', 'bids', 'supplier', '"request for proposal"'],
                'leadership' => ['leadership', 'administration', 'president', 'rector', '"vice chancellor"', 'directory'],
                'news' => ['news', 'press', 'announcements', 'media'],
                'hr_it' => ['"human resources"', 'HR', '"information technology"', 'IT', 'CIO'],
            ],
            'fr' => [
                'procurement' => ['achats', '"appels d offres"', 'marches', 'fournisseurs'],
                'leadership' => ['direction', 'rectorat', 'administration', 'annuaire'],
                'news' => ['actualites', 'presse', 'communiques'],
                'hr_it' => ['"ressources humaines"', 'informatique', '"systemes information"'],
            ],
            'es' => [
                'procurement' => ['licitaciones', 'contrataciones', 'proveedores', 'compras'],
                'leadership' => ['autoridades', 'rector', 'directorio', 'administracion'],
                'news' => ['noticias', 'prensa', 'comunicados'],
                'hr_it' => ['"recursos humanos"', 'informatica', 'tecnologia'],
            ],
            'pt' => [
                'procurement' => ['aquisicoes', 'licitacoes', 'contratacoes', 'fornecedores'],
                'leadership' => ['reitoria', 'direcao', 'administracao', 'diretorio'],
                'news' => ['noticias', 'imprensa', 'comunicados'],
                'hr_it' => ['"recursos humanos"', 'informatica', 'tecnologia'],
            ],
            'ar' => [
                'procurement' => ['مناقصات', 'مشتريات', 'عطاءات', 'موردين'],
                'leadership' => ['الإدارة', 'الرئيس', 'العميد', 'دليل'],
                'news' => ['أخبار', 'بيانات', 'إعلانات'],
                'hr_it' => ['الموارد البشرية', 'تقنية المعلومات'],
            ],
        ];

        $selected = $terms[$language] ?? $terms['en'];

        return collect($selected)
            ->mapWithKeys(fn (array $group, string $key) => [$key => collect($group)->merge($terms['en'][$key])->unique()->values()])
            ->all();
    }

    private function candidateFromSerpResult(MarketOrganization $organization, array $result): ?array
    {
        $url = trim((string) ($result['link'] ?? ''));
        $title = trim((string) ($result['title'] ?? ''));
        $snippet = trim((string) ($result['snippet'] ?? ''));
        $domain = $this->domainFromUrl($url);

        if ($url === '' || $domain === '' || $title === '') {
            return null;
        }

        $kind = $this->classifyUrl($url, $title . ' ' . $snippet);

        if ($kind === null) {
            if (blank($organization->website_url) && $this->looksLikeOrganizationWebsite($organization, $url, $title . ' ' . $snippet)) {
                $kind = 'website_url';
            } else {
                return null;
            }
        }

        return [
            'kind' => $kind,
            'url' => $url,
            'domain' => $domain,
            'title' => Str::limit($title, 255, ''),
            'snippet' => Str::limit($snippet, 500, ''),
            'same_domain' => $organization->website_domain && Str::contains($domain, (string) $organization->website_domain),
        ];
    }

    private function candidatesFromPageLinks(MarketOrganization $organization, string $sourceUrl, string $html): Collection
    {
        $baseDomain = $this->domainFromUrl((string) ($organization->website_url ?: $sourceUrl));

        if ($baseDomain === '') {
            return collect();
        }

        preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match) use ($sourceUrl) {
                $url = $this->absoluteUrl(trim((string) $match[1]), $sourceUrl);
                $label = $this->plainText((string) $match[2]);

                return [$url, $label];
            })
            ->filter(fn (array $link) => $link[0] !== '')
            ->filter(fn (array $link) => $this->domainFromUrl($link[0]) !== '')
            ->filter(fn (array $link) => Str::endsWith($this->domainFromUrl($link[0]), $baseDomain))
            ->map(function (array $link) {
                [$url, $label] = $link;
                $kind = $this->classifyUrl($url, $label);

                if ($kind === null || $kind === 'website_url') {
                    return null;
                }

                return [
                    'kind' => $kind,
                    'url' => $url,
                    'domain' => $this->domainFromUrl($url),
                    'title' => Str::limit($label ?: $url, 255, ''),
                    'snippet' => 'Discovered by direct crawl from site links.',
                    'same_domain' => true,
                ];
            })
            ->filter()
            ->unique(fn (array $candidate) => $candidate['kind'] . '|' . $candidate['url'])
            ->values();
    }

    private function classifyUrl(string $url, string $text): ?string
    {
        $haystack = Str::lower($url . ' ' . $text);

        return match (true) {
            Str::contains($haystack, ['procurement', 'tender', 'bid', 'supplier', 'vendor', 'licitacion', 'licitaciones', 'achats', 'appels', 'aquisicoes', 'مناقصات', 'مشتريات']) => 'procurement_page_url',
            Str::contains($haystack, ['leadership', 'administration', 'president', 'rector', 'vice-chancellor', 'vice chancellor', 'directory', 'annuaire', 'autoridades', 'reitoria', 'الإدارة']) => 'leadership_page_url',
            Str::contains($haystack, ['human-resources', 'human resources', '/hr', 'ressources humaines', 'recursos humanos']) => 'hr_page_url',
            Str::contains($haystack, ['information-technology', 'information technology', '/it', 'informatique', 'informatica', 'technology services']) => 'it_page_url',
            Str::contains($haystack, ['news', 'press', 'announcement', 'announcements', 'media', 'media centre', 'media center', 'communications', 'public relations', 'actualites', 'noticias', 'comunicados', 'أخبار']) => 'news_page_url',
            Str::contains($haystack, ['official website', 'university', 'college']) => 'website_url',
            default => null,
        };
    }

    private function applyBestCandidates(MarketOrganization $organization, Collection $candidates): array
    {
        $updates = [];

        foreach (['website_url', 'procurement_page_url', 'leadership_page_url', 'hr_page_url', 'it_page_url', 'news_page_url'] as $field) {
            if (filled($organization->{$field})) {
                continue;
            }

            $candidate = $candidates
                ->where('kind', $field)
                ->sortByDesc(fn (array $candidate) => $candidate['same_domain'] ? 1 : 0)
                ->first();

            if ($candidate) {
                $updates[$field] = $candidate['url'];
            }
        }

        if (empty($organization->website_domain) && ! empty($updates['website_url'])) {
            $updates['website_domain'] = $this->domainFromUrl((string) $updates['website_url']);
        }

        if (count($updates) > 0) {
            $organization->update($updates);
        }

        return array_values(array_diff(array_keys($updates), ['market_crawler_id']));
    }

    private function crawlUrls(MarketOrganization $organization): Collection
    {
        return collect([
            ['type' => 'main_website', 'url' => $organization->website_url],
            ['type' => 'procurement', 'url' => $organization->procurement_page_url],
            ['type' => 'leadership', 'url' => $organization->leadership_page_url],
            ['type' => 'hr', 'url' => $organization->hr_page_url],
            ['type' => 'it', 'url' => $organization->it_page_url],
            ['type' => 'announcements_press', 'url' => $organization->news_page_url],
        ])
            ->filter(fn (array $page) => filled($page['url']))
            ->map(function (array $page) {
                $page['url'] = $this->normalizeCrawlUrl((string) $page['url']);

                return $page;
            })
            ->filter(fn (array $page) => filled($page['url']))
            ->unique('url')
            ->values();
    }

    private function contactsFromText(MarketOrganization $organization, MarketCrawler $crawler, string $url, string $pageType, string $text): Collection
    {
        $text = $this->cleanText($text);
        preg_match_all('/(?<![A-Z0-9._%+\-])[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,10}(?![A-Z0-9.\-])/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email) => $this->cleanEmail($email))
            ->filter(fn (string $email) => ! Str::contains($email, ['example.com', '.png', '.jpg']))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->take(25)
            ->map(function (string $email) use ($organization, $crawler, $url, $pageType, $text) {
                $context = $this->cleanText($this->emailContext($text, $email));
                [$personName, $jobTitle] = $this->personAndTitleFromContext($context);
                [$personName, $jobTitle, $verificationStatus] = $this->normalizeCapturedContactIdentity($personName, $jobTitle, $email);

                return [
                    'market_organization_id' => $organization->id,
                    'market_crawler_id' => $crawler->id,
                    'contact_type' => $this->contactTypeFromUrl($url, $pageType),
                    'person_name' => $this->cleanText((string) $personName) ?: null,
                    'job_title' => $this->cleanText((string) $jobTitle) ?: null,
                    'email' => $email,
                    'notes' => 'Extracted from organization crawler page type: ' . str_replace('_', ' ', $pageType),
                    'source_url' => $url,
                    'context_excerpt' => $context,
                    'verification_status' => $verificationStatus,
                    'extracted_at' => now(),
                    'source_fingerprint' => hash('sha256', $organization->id . '|' . $email . '|' . $url),
                ];
            })
            ->values();
    }

    /**
     * @return array{0:?string,1:?string,2:string}
     */
    public function normalizeCapturedContactIdentity(?string $personName, ?string $jobTitle, string $email): array
    {
        $personName = $this->cleanText((string) $personName) ?: null;
        $jobTitle = $this->cleanText((string) $jobTitle) ?: null;
        $roleFromEmail = $this->roleFromGenericEmail($email);
        $verificationStatus = 'published';

        if ($personName && $this->looksInvalidPersonName($personName)) {
            $personName = null;
            $verificationStatus = 'needs_name_research';
        }

        if ($jobTitle && $this->looksInvalidJobTitle($jobTitle)) {
            $jobTitle = null;
        }

        if ($roleFromEmail && (! $jobTitle || $this->roleConflictsWithEmail($roleFromEmail, $jobTitle) || $personName === $roleFromEmail)) {
            $jobTitle = $roleFromEmail;
            $verificationStatus = 'needs_name_research';
        }

        if (! $personName && $roleFromEmail && ! $this->isGenericMailboxOnly($email)) {
            $personName = $roleFromEmail;
            $verificationStatus = 'needs_name_research';
        }

        if ($roleFromEmail && $personName === $roleFromEmail) {
            $jobTitle = $roleFromEmail;
            $verificationStatus = 'needs_name_research';
        }

        if (! $personName && ! $roleFromEmail && $this->looksLikePersonalEmailNeedingNameResearch($email)) {
            $verificationStatus = 'needs_name_research';
        }

        return [$personName, $jobTitle, $verificationStatus];
    }

    private function contactTypeFromUrl(string $url, string $pageType): string
    {
        if (in_array($pageType, ['procurement', 'leadership', 'hr', 'it'], true)) {
            return $pageType;
        }

        return match (true) {
            Str::contains(Str::lower($url), ['procurement', 'tender', 'supplier', 'vendor']) => 'procurement',
            Str::contains(Str::lower($url), ['leadership', 'administration', 'rector', 'president']) => 'leadership',
            Str::contains(Str::lower($url), ['human-resources', '/hr']) => 'hr',
            Str::contains(Str::lower($url), ['information-technology', '/it']) => 'it',
            Str::contains(Str::lower($url), ['news', 'press', 'announcement', 'announcements', 'media', 'media-centre', 'media-center', 'communications', 'public-relations']) => 'announcements_press',
            default => 'general',
        };
    }

    private function personAndTitleFromContext(string $context): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $context) ?? '');

        if ($clean === '') {
            return [null, null];
        }

        $titles = [
            'president',
            'vice president',
            'rector',
            'vice rector',
            'vice-chancellor',
            'vice chancellor',
            'chancellor',
            'provost',
            'registrar',
            'director',
            'dean',
            'head',
            'chief',
            'procurement officer',
            'procurement',
        ];

        $titlePattern = implode('|', array_map(fn (string $title) => preg_quote($title, '/'), $titles));

        if (preg_match('/([A-Z][a-zA-Z.\'-]+(?:\s+[A-Z][a-zA-Z.\'-]+){1,3})[^.]{0,80}\b(' . $titlePattern . ')\b/i', $clean, $match)) {
            return [Str::limit(trim($match[1]), 120, ''), Str::limit(trim($match[2]), 120, '')];
        }

        if (preg_match('/\b(' . $titlePattern . ')\b[^.]{0,80}([A-Z][a-zA-Z.\'-]+(?:\s+[A-Z][a-zA-Z.\'-]+){1,3})/i', $clean, $match)) {
            return [Str::limit(trim($match[2]), 120, ''), Str::limit(trim($match[1]), 120, '')];
        }

        return [null, null];
    }

    private function looksInvalidPersonName(string $value): bool
    {
        $clean = Str::lower(trim($value));

        if ($clean === '') {
            return true;
        }

        if (Str::contains($clean, [
            '@',
            'www.',
            'http',
            '.edu',
            '.ac.',
            '.com',
            '.org',
            '.net',
            'p.o. box',
            'p.o.',
            'po box',
            'box ',
            'university',
            'college',
            'school',
            'latest news',
            'appoints',
            'admission',
            'admissions',
            'phone',
            'tel:',
            'fax',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
            'offday',
            'office hour',
            'facebook',
            'youtube',
            'instagram',
            'linkedin',
        ])) {
            return true;
        }

        if (preg_match('/\d/', $clean)) {
            return true;
        }

        if (str_word_count($clean) > 5) {
            return true;
        }

        return ! preg_match('/^[\p{L}][\p{L}.\'\-]+(?:\s+[\p{L}][\p{L}.\'\-]+){0,4}$/u', trim($value));
    }

    private function looksInvalidJobTitle(string $value): bool
    {
        $clean = Str::lower(trim($value));

        return $clean === ''
            || Str::contains($clean, ['@', 'www.', 'http', '.edu', '.com', 'p.o. box', 'po box', 'phone', 'fax'])
            || str_word_count($clean) > 8;
    }

    private function roleFromGenericEmail(string $email): ?string
    {
        $local = Str::of(Str::lower($email))
            ->before('@')
            ->replace(['-', '_'], '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.')
            ->toString();

        $roleMap = [
            'registrar' => 'Registrar',
            'vc' => 'Vice Chancellor',
            'vcoffice' => 'Vice Chancellor',
            'vice.chancellor' => 'Vice Chancellor',
            'chancellor' => 'Chancellor',
            'rector' => 'Rector',
            'president' => 'President',
            'provost' => 'Provost',
            'director' => 'Director',
            'dean' => 'Dean',
            'admission' => 'Admissions Office',
            'admissions' => 'Admissions Office',
            'procurement' => 'Procurement Office',
            'tenders' => 'Procurement Office',
            'supplier' => 'Supplier Relations',
            'hr' => 'Human Resources',
            'human.resources' => 'Human Resources',
            'it' => 'Information Technology',
            'ict' => 'Information and Communications Technology',
            'finance' => 'Finance Office',
            'bursar' => 'Bursar',
            'controller' => 'Controller',
            'secretary' => 'Secretary',
        ];

        if (isset($roleMap[$local])) {
            return $roleMap[$local];
        }

        $parts = collect(explode('.', $local))->filter()->values();

        foreach ($roleMap as $needle => $role) {
            $needleParts = explode('.', $needle);
            $matched = count($needleParts) === 1
                ? $parts->contains($needle)
                : Str::contains($local, $needle);

            if (! $matched) {
                continue;
            }

            if ($parts->contains('mba')) {
                return $role . ' - MBA Program';
            }

            return $role;
        }

        return null;
    }

    private function roleConflictsWithEmail(string $roleFromEmail, string $jobTitle): bool
    {
        $role = Str::lower($roleFromEmail);
        $title = Str::lower($jobTitle);

        if (Str::contains($role, ['vice chancellor']) && ! Str::contains($title, ['vice chancellor'])) {
            return true;
        }

        if (Str::contains($role, ['director']) && ! Str::contains($title, ['director'])) {
            return true;
        }

        if (Str::contains($role, ['registrar']) && ! Str::contains($title, ['registrar'])) {
            return true;
        }

        return false;
    }

    private function isGenericMailboxOnly(string $email): bool
    {
        $local = Str::of(Str::lower($email))->before('@')->toString();

        return in_array($local, [
            'info',
            'contact',
            'enquiries',
            'enquiry',
            'mail',
            'admin',
            'webmaster',
            'support',
            'helpdesk',
        ], true);
    }

    private function looksLikePersonalEmailNeedingNameResearch(string $email): bool
    {
        $local = Str::of(Str::lower($email))
            ->before('@')
            ->replace(['-', '_'], '.')
            ->replaceMatches('/\.+/', '.')
            ->trim('.')
            ->toString();

        if ($local === '' || $this->isGenericMailboxOnly($email) || $this->roleFromGenericEmail($email)) {
            return false;
        }

        if (Str::contains($local, ['noreply', 'no.reply', 'donotreply', 'postmaster', 'mailer-daemon'])) {
            return false;
        }

        if (preg_match('/^\d+$/', $local)) {
            return false;
        }

        return (bool) preg_match('/^[a-z][a-z.]{2,40}$/', $local);
    }

    private function emailContext(string $text, string $email, int $radius = 200): string
    {
        $position = stripos($text, $email);

        if ($position === false) {
            return '';
        }

        return Str::limit(
            trim(mb_substr($text, max(0, $position - $radius), ($radius * 2) + strlen($email))),
            max(500, $radius * 2),
            ''
        );
    }

    private function recordRun(MarketCrawler $crawler, ?MarketOrganization $organization, string $type, string $status, $startedAt, array $data): void
    {
        MarketCrawlerRun::create([
            'market_crawler_id' => $crawler->id,
            'market_organization_id' => $organization?->id,
            'run_type' => $type,
            'status' => $status,
            'query_text' => $data['query_text'] ?? null,
            'request_url' => $data['request_url'] ?? null,
            'url_checked' => $data['url_checked'] ?? null,
            'http_status' => $data['http_status'] ?? null,
            'items_found' => $data['items_found'] ?? 0,
            'urls_updated' => $data['urls_updated'] ?? 0,
            'contacts_found' => $data['contacts_found'] ?? 0,
            'result_payload' => $data['result_payload'] ?? null,
            'response_excerpt' => $data['response_excerpt'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'started_at' => $startedAt,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param array<int, string> $pageErrors
     */
    private function createCrawlerFollowUpTask(MarketOrganization $organization, MarketCrawler $crawler, array $pageErrors): void
    {
        $title = 'Review unreachable crawler target: ' . $organization->name;
        $notes = 'The ' . $crawler->name . ' could not reach one or more saved URLs for this organization.'
            . "\n\nErrors:\n- " . implode("\n- ", array_slice($pageErrors, 0, 5))
            . "\n\nNext step: retry later, check the domain manually, or replace the stale website/page URL.";

        MarketOrganizationTask::updateOrCreate(
            [
                'market_organization_id' => $organization->id,
                'title' => $title,
                'status' => 'open',
            ],
            [
                'notes' => $notes,
                'task_type' => 'crawler_follow_up',
                'due_at' => now()->addDays(2),
                'completed_at' => null,
            ],
        );
    }

    private function closeCrawlerFollowUpTasks(MarketOrganization $organization): void
    {
        MarketOrganizationTask::query()
            ->where('market_organization_id', $organization->id)
            ->where('task_type', 'crawler_follow_up')
            ->where('status', 'open')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }

    private function domainFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return Str::of($host)->lower()->replace('www.', '')->toString();
    }

    private function looksLikeOrganizationWebsite(MarketOrganization $organization, string $url, string $text): bool
    {
        $domain = $this->domainFromUrl($url);

        if ($domain === '' || Str::contains($domain, [
            'facebook.',
            'linkedin.',
            'twitter.',
            'x.com',
            'wikipedia.',
            'youtube.',
            'instagram.',
            'google.',
        ])) {
            return false;
        }

        $nameTokens = Str::of(Str::ascii((string) $organization->name))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->explode(' ')
            ->filter(fn (string $token) => strlen($token) >= 4)
            ->reject(fn (string $token) => in_array($token, [
                'university',
                'college',
                'national',
                'social',
                'security',
                'insurance',
                'pension',
                'provident',
                'board',
                'fund',
                'authority',
                'organization',
                'organisation',
                'administration',
            ], true))
            ->take(5);

        $haystack = Str::lower($domain . ' ' . Str::ascii($text));

        return $nameTokens->contains(fn (string $token) => Str::contains($haystack, $token));
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if ($url === '' || Str::startsWith($url, ['mailto:', 'tel:', '#', 'javascript:'])) {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! $host) {
            return '';
        }

        if (Str::startsWith($url, '//')) {
            return $scheme . ':' . $url;
        }

        if (Str::startsWith($url, '/')) {
            return $scheme . '://' . $host . $url;
        }

        $path = (string) (parse_url($baseUrl, PHP_URL_PATH) ?: '/');
        $directory = rtrim(Str::beforeLast($path, '/'), '/');

        return $scheme . '://' . $host . ($directory === '' ? '' : '/' . ltrim($directory, '/')) . '/' . ltrim($url, '/');
    }

    private function normalizeCrawlUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        $url = preg_replace('#://www\.www\.#i', '://www.', $url) ?? $url;
        $url = preg_replace('#://www\.www\.#i', '://www.', $url) ?? $url;

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . ltrim($url, '/');
        }

        return $url;
    }

    private function plainText(string $html): string
    {
        $html = $this->cleanText($html);

        return $this->cleanText(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function cleanEmail(string $email): string
    {
        $email = Str::lower($this->cleanText($email));
        $email = preg_replace('/\s+/', '', $email) ?? '';
        $email = preg_replace('/^\d{4,}(?=[a-z])/', '', $email) ?? $email;

        return trim($email, " \t\n\r\0\x0B.,;:()[]{}<>\"'");
    }

    private function cleanText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        $converted = $converted === false ? $value : $converted;
        $converted = str_replace(["\xC2\x80\xC2\x93", "\xC2\x80\xC2\x99"], ['-', "'"], $converted);
        $converted = preg_replace('/[^\P{C}\t\n\r]+/u', '', $converted) ?? $converted;

        return trim($converted);
    }

    private function httpOptions(): array
    {
        $verifySsl = filter_var(env('SERPAPI_VERIFY_SSL', true), FILTER_VALIDATE_BOOLEAN);
        $options = ['verify' => $verifySsl];
        $caBundle = trim((string) env('SERPAPI_CA_BUNDLE', ''));

        if ($verifySsl && $caBundle !== '' && is_file($caBundle)) {
            $options['verify'] = $caBundle;
        }

        return $options;
    }
}
