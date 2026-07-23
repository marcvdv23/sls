<?php

namespace App\Services;

use App\Models\MarketCrawler;
use App\Models\MarketCrawlerRun;
use App\Models\MarketOrganization;
use App\Support\SocialSecurityAdminNameCleaner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class BankDomainGuessService
{
    /**
     * @return array{eligible:int,processed:int,ready_for_crawl:int,domain_guess_not_found:int,guesses:int,checks:int,items:array<int,array<string,mixed>>,errors:array<int,string>}
     */
    public function run(
        int $limit = 50,
        ?string $region = null,
        array $countries = [],
        bool $retryPreviousNotFound = true,
        bool $dryRun = false,
        int $pauseSeconds = 1,
        int $minimumConfidence = 70,
        int $maxChecksPerBank = 12,
        ?callable $onItem = null,
    ): array {
        $crawler = $this->bankCrawler();
        $query = $this->eligibleQuery($region, $countries, $retryPreviousNotFound);
        $eligible = (clone $query)->count();
        $organizations = $query->limit(max(1, $limit))->get();

        $processed = 0;
        $ready = 0;
        $notFound = 0;
        $guessCount = 0;
        $checkCount = 0;
        $items = [];
        $errors = [];

        foreach ($organizations as $index => $organization) {
            $processed++;
            $startedAt = now();
            $guesses = $this->domainGuesses($organization);
            $guessCount += $guesses->count();
            $best = null;
            $checked = [];

            $checks = 0;

            foreach ($guesses->take(max(1, $maxChecksPerBank)) as $guess) {
                $checks++;
                $checkCount++;
                $candidate = $this->checkDomain($organization, $guess);
                $checked[] = $candidate;

                if ($best === null || $candidate['confidence'] > $best['confidence']) {
                    $best = $candidate;
                }

                if ($candidate['confidence'] >= $minimumConfidence) {
                    break;
                }
            }

            $status = ($best && $best['confidence'] >= $minimumConfidence) ? 'ready_for_crawl' : 'domain_guess_not_found';

            if ($status === 'ready_for_crawl') {
                $ready++;

                if (! $dryRun) {
                    $organization->update([
                        'market_crawler_id' => $crawler->id,
                        'website_url' => $best['url'],
                        'website_domain' => $best['domain'],
                        'last_error' => null,
                    ]);
                }
            } else {
                $notFound++;

                if (! $dryRun) {
                    $organization->update([
                        'market_crawler_id' => $crawler->id,
                        'last_error' => 'Bank domain guess not found after ' . $guesses->count() . ' deterministic guess(es).',
                    ]);
                }
            }

            MarketCrawlerRun::create([
                'market_crawler_id' => $crawler->id,
                'market_organization_id' => $organization->id,
                'run_type' => 'bank_domain_guess',
                'status' => $dryRun ? 'dry_run_' . $status : $status,
                'items_found' => $guesses->count(),
                'urls_updated' => (! $dryRun && $status === 'ready_for_crawl') ? 2 : 0,
                'result_payload' => [
                    'best' => $best,
                    'guesses' => $guesses->values()->all(),
                        'checked' => array_slice($checked, 0, max(1, $maxChecksPerBank)),
                        'checks' => $checks,
                    'minimum_confidence' => $minimumConfidence,
                    'dry_run' => $dryRun,
                ],
                'response_excerpt' => $best
                    ? ($best['domain'] . ' confidence ' . $best['confidence'] . '% - ' . implode('; ', $best['reasons']))
                    : 'No candidate checked.',
                'error_message' => $status === 'ready_for_crawl' ? null : 'No high-confidence bank domain guess found.',
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            $item = [
                'id' => $organization->id,
                'name' => SocialSecurityAdminNameCleaner::repairMojibake((string) $organization->name),
                'country' => SocialSecurityAdminNameCleaner::repairMojibake((string) $organization->country),
                'country_iso' => $organization->country_iso,
                'status' => $status,
                'guesses' => $guesses->count(),
                'checked' => $checks,
                'best_domain' => $best['domain'] ?? null,
                'best_url' => $best['url'] ?? null,
                'confidence' => $best['confidence'] ?? 0,
                'reasons' => $best['reasons'] ?? [],
            ];
            $items[] = $item;

            if ($onItem) {
                $onItem($item, [
                    'eligible' => $eligible,
                    'processed' => $processed,
                    'ready_for_crawl' => $ready,
                    'domain_guess_not_found' => $notFound,
                    'guesses' => $guessCount,
                    'checks' => $checkCount,
                ]);
            }

            if ($pauseSeconds > 0 && $index + 1 < $organizations->count()) {
                sleep($pauseSeconds);
            }
        }

        return [
            'eligible' => $eligible,
            'processed' => $processed,
            'ready_for_crawl' => $ready,
            'domain_guess_not_found' => $notFound,
            'guesses' => $guessCount,
            'checks' => $checkCount,
            'items' => $items,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function domainGuesses(MarketOrganization $organization): Collection
    {
        $tokens = $this->bankNameTokens((string) $organization->name, $organization);
        $rawTokens = collect($this->domainNameTokens((string) $organization->name))
            ->reject(fn (string $token) => in_array($token, $this->legalSuffixes(), true))
            ->values();
        $descriptorTokens = collect($tokens)->filter(fn (string $token) => in_array($token, $this->bankDescriptors(), true))->values();
        $nameTokens = collect($tokens)->reject(fn (string $token) => in_array($token, $this->legalSuffixes(), true))->values();
        $primaryBases = collect();
        $secondaryBases = collect();
        $fallbackBases = collect();

        if ($nameTokens->isEmpty()) {
            return collect();
        }

        $rawAcronym = $rawTokens
            ->reject(fn (string $token) => in_array($token, ['the', 'and', 'of', 'for', 'de', 'du', 'la', 'le', 'el'], true))
            ->map(fn (string $token) => Str::substr($token, 0, 1))
            ->implode('');

        $primaryBases->push($nameTokens->implode(''));
        $secondaryBases->push($nameTokens->implode('-'));

        if (! $nameTokens->contains('bank')) {
            $primaryBases->push($nameTokens->take(3)->implode('') . 'bank');
            $secondaryBases->push($nameTokens->take(3)->implode('-') . '-bank');
        }

        if ($nameTokens->contains('bank')) {
            $bankIndex = $nameTokens->search('bank');
            $throughBank = $nameTokens->take($bankIndex + 1);
            $primaryBases->push($throughBank->implode(''));
            $secondaryBases->push($throughBank->implode('-'));
        }

        if ($descriptorTokens->isNotEmpty()) {
            $nonDescriptor = $nameTokens->reject(fn (string $token) => $descriptorTokens->contains($token))->values();
            if ($nonDescriptor->isNotEmpty()) {
                $secondaryBases->push($nonDescriptor->take(2)->merge($descriptorTokens)->implode(''));
                $secondaryBases->push($nonDescriptor->take(2)->merge($descriptorTokens)->implode('-'));
            }
        }

        $acronym = $nameTokens
            ->reject(fn (string $token) => in_array($token, ['the', 'and', 'of'], true))
            ->map(fn (string $token) => Str::substr($token, 0, 1))
            ->implode('');

        if (strlen($rawAcronym) >= 2) {
            $fallbackBases->push($rawAcronym);
            $fallbackBases->push($rawAcronym . 'bank');
        }

        if (strlen($acronym) >= 2) {
            $fallbackBases->push($acronym);
            $fallbackBases->push($acronym . 'bank');
        }

        $first = (string) $nameTokens->first();
        if ($first !== '') {
            $secondaryBases->push($first . 'bank');
            $secondaryBases->push($first . '-bank');
            $secondaryBases->push($first);
        }

        $tlds = $this->priorityTlds((string) $organization->country_iso);

        $primaryGuesses = $primaryBases
            ->map(fn (string $base) => trim($base, '-'))
            ->filter(fn (string $base) => strlen($base) >= 3)
            ->unique()
            ->flatMap(fn (string $base) => collect($tlds)->map(fn (string $tld) => $base . $tld))
            ->values();

        $fallbackGuesses = $fallbackBases
            ->map(fn (string $base) => trim($base, '-'))
            ->filter(fn (string $base) => strlen($base) >= 2)
            ->unique()
            ->flatMap(fn (string $base) => collect($tlds)->map(fn (string $tld) => $base . $tld))
            ->values();

        $secondaryGuesses = $secondaryBases
            ->map(fn (string $base) => trim($base, '-'))
            ->filter(fn (string $base) => strlen($base) >= 3)
            ->unique()
            ->flatMap(fn (string $base) => collect($tlds)->map(fn (string $tld) => $base . $tld))
            ->values();

        return $primaryGuesses
            ->merge($fallbackGuesses)
            ->merge($secondaryGuesses)
            ->unique()
            ->take(72)
            ->values();
    }

    private function eligibleQuery(?string $region, array $countries, bool $retryPreviousNotFound)
    {
        return MarketOrganization::query()
            ->where('organization_type', 'financial_institution')
            ->where('industry', 'banking_finance')
            ->where('status', '<>', 'sanctioned')
            ->where(function ($query) {
                $query->whereNull('website_url')->orWhere('website_url', '');
            })
            ->where(function ($query) {
                $query->whereNull('website_domain')->orWhere('website_domain', '');
            })
            ->when($region, fn ($query) => $query->where('region', $region))
            ->when($countries !== [], function ($query) use ($countries) {
                $query->where(function ($inner) use ($countries) {
                    foreach ($countries as $country) {
                        $inner->orWhere('country_iso', Str::upper($country))
                            ->orWhere('country', 'like', '%' . $country . '%');
                    }
                });
            })
            ->when(! $retryPreviousNotFound, fn ($query) => $query->where(function ($inner) {
                $inner->whereNull('last_error')
                    ->orWhere('last_error', 'not like', 'Bank domain guess not found%');
            }))
            ->orderBy('region')
            ->orderBy('country')
            ->orderBy('name');
    }

    /**
     * @return array{domain:string,url:?string,confidence:int,reasons:array<int,string>,http_status:?int}
     */
    private function checkDomain(MarketOrganization $organization, string $domain): array
    {
        $urls = ['https://www.' . $domain, 'https://' . $domain];
        $best = [
            'domain' => $domain,
            'url' => null,
            'confidence' => 0,
            'reasons' => [],
            'http_status' => null,
        ];

        if (! $this->hasDnsRecord($domain)) {
            $best['reasons'] = ['no DNS record found'];

            return $best;
        }

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(4)
                    ->connectTimeout(2)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 1G-SLS Bank Domain Verifier'])
                    ->withOptions($this->httpOptions())
                    ->get($url);
            } catch (Throwable $exception) {
                continue;
            }

            $candidate = $this->scoreResponse($organization, $domain, $url, $response->status(), (string) $response->body());

            if ($candidate['confidence'] > $best['confidence']) {
                $best = $candidate;
            }

            if ($candidate['confidence'] >= 70) {
                break;
            }
        }

        return $best;
    }

    /**
     * @return array{domain:string,url:string,confidence:int,reasons:array<int,string>,http_status:int}
     */
    private function scoreResponse(MarketOrganization $organization, string $domain, string $url, int $status, string $body): array
    {
        $confidence = 0;
        $reasons = [];
        $text = Str::of(Str::lower(Str::ascii(strip_tags($body))))
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
        $domainRoot = Str::before($domain, '.');
        $tokens = collect($this->bankNameTokens((string) $organization->name, $organization));
        $rawTokens = collect($this->domainNameTokens((string) $organization->name))
            ->reject(fn (string $token) => in_array($token, array_merge(['the', 'and', 'of', 'for', 'de', 'du', 'la', 'le', 'el'], $this->legalSuffixes()), true))
            ->values();
        $rawAcronym = collect($this->domainNameTokens((string) $organization->name))
            ->reject(fn (string $token) => in_array($token, array_merge(['the', 'and', 'of', 'for', 'de', 'du', 'la', 'le', 'el'], $this->legalSuffixes()), true))
            ->map(fn (string $token) => Str::substr($token, 0, 1))
            ->implode('');
        $isAcronymDomain = strlen($rawAcronym) >= 2 && $domainRoot === $rawAcronym;
        $firstLegalToken = (string) $rawTokens->first();

        if ($status >= 200 && $status < 400) {
            $confidence += 25;
            $reasons[] = 'site reachable';
        } elseif (in_array($status, [401, 403, 405], true)) {
            $confidence += 15;
            $reasons[] = 'site exists but blocks generic fetch';
        }

        if ($isAcronymDomain) {
            $confidence += 18;
            $reasons[] = 'domain matches bank acronym fallback';
        } elseif (Str::contains($domainRoot, 'bank')) {
            $confidence += 12;
            $reasons[] = 'domain includes bank';
        } else {
            $confidence -= 15;
            $reasons[] = 'domain does not include bank';
        }

        $countryIso = Str::lower((string) $organization->country_iso);
        if ($countryIso !== '' && Str::endsWith($domain, '.' . $countryIso)) {
            $confidence += 10;
            $reasons[] = 'uses country-code domain';
        }

        if (strlen($firstLegalToken) >= 4 && Str::contains($domainRoot, $firstLegalToken)) {
            $confidence += 12;
            $reasons[] = 'domain includes first legal-name word';
        } elseif (strlen($firstLegalToken) >= 4 && ! $isAcronymDomain) {
            $confidence -= 25;
            $reasons[] = 'domain omits first legal-name word';
        }

        $domainMatchedTokens = $tokens
            ->filter(fn (string $token) => strlen($token) >= 4)
            ->filter(fn (string $token) => Str::contains($domainRoot, $token))
            ->unique()
            ->values();

        $pageMatchedTokens = $tokens
            ->filter(fn (string $token) => strlen($token) >= 4)
            ->filter(fn (string $token) => Str::contains($text, $token))
            ->unique()
            ->values();

        $confidence += min(18, $domainMatchedTokens->count() * 6);
        $confidence += min(35, $pageMatchedTokens->count() * 12);

        if ($domainMatchedTokens->isNotEmpty()) {
            $reasons[] = 'domain matched name tokens: ' . $domainMatchedTokens->implode(', ');
        }

        if ($pageMatchedTokens->isNotEmpty()) {
            $reasons[] = 'page matched name tokens: ' . $pageMatchedTokens->implode(', ');
        }

        $hasBankingLanguage = $this->containsBankingLanguage($text);
        if ($hasBankingLanguage) {
            $confidence += 15;
            $reasons[] = 'banking language found';
        }

        $country = Str::lower(Str::ascii((string) $organization->country));
        if ($country !== '' && Str::contains($text, $country)) {
            $confidence += 8;
            $reasons[] = 'country mentioned';
        }

        if ($this->blockedDomain($domain)) {
            $confidence = 0;
            $reasons[] = 'blocked/aggregator domain';
        }

        if (Str::contains($text, ['domain for sale', 'buy this domain', 'this domain is for sale', 'sedo.com', 'afternic', 'dan.com', 'parkingcrew'])) {
            $confidence = 0;
            $reasons[] = 'domain appears parked or for sale';
        }

        if (! $this->pageConfirmsOrganization($organization, $tokens, $pageMatchedTokens, $text, $hasBankingLanguage)) {
            $confidence = min($confidence, 55);
            $reasons[] = 'page does not mention enough organization-name terms';
        }

        return [
            'domain' => $domain,
            'url' => $url,
            'confidence' => max(0, min(100, $confidence)),
            'reasons' => $reasons,
            'http_status' => $status,
        ];
    }

    private function hasDnsRecord(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        return checkdnsrr($domain, 'A')
            || checkdnsrr($domain, 'AAAA')
            || checkdnsrr($domain, 'CNAME')
            || checkdnsrr('www.' . $domain, 'A')
            || checkdnsrr('www.' . $domain, 'AAAA')
            || checkdnsrr('www.' . $domain, 'CNAME');
    }

    /**
     * @param Collection<int,string> $tokens
     * @param Collection<int,string> $pageMatchedTokens
     */
    private function pageConfirmsOrganization(
        MarketOrganization $organization,
        Collection $tokens,
        Collection $pageMatchedTokens,
        string $text,
        bool $hasBankingLanguage,
    ): bool {
        $namePhrase = collect($this->bankNameTokens((string) $organization->name, $organization))
            ->reject(fn (string $token) => in_array($token, $this->legalSuffixes(), true))
            ->implode(' ');

        if ($namePhrase !== '' && strlen($namePhrase) >= 8 && Str::contains($text, $namePhrase)) {
            return true;
        }

        $countryTokens = collect($this->domainNameTokens((string) $organization->country))
            ->push(Str::lower((string) $organization->country_iso))
            ->filter()
            ->values()
            ->all();

        $specificPageTokens = $pageMatchedTokens
            ->reject(fn (string $token) => in_array($token, array_merge($this->genericConfirmationTokens(), $countryTokens), true))
            ->values();

        if ($specificPageTokens->count() >= 2) {
            return true;
        }

        if ($specificPageTokens->count() >= 1 && $hasBankingLanguage) {
            return true;
        }

        $confirmableTokens = $tokens
            ->filter(fn (string $token) => strlen($token) >= 4)
            ->reject(fn (string $token) => in_array($token, array_merge($this->genericConfirmationTokens(), $countryTokens), true))
            ->values();

        return $confirmableTokens->isEmpty()
            ? ($pageMatchedTokens->count() >= 2 && $hasBankingLanguage)
            : $specificPageTokens->count() >= min(2, $confirmableTokens->count());
    }

    private function containsBankingLanguage(string $text): bool
    {
        return Str::contains($text, [
            ' bank ', ' banking ', ' banque ', ' banco ', ' banca ', ' credit ', ' savings ',
            ' deposit ', ' loan ', ' branch ', ' atm ', ' financial services ',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function bankNameTokens(string $name, ?MarketOrganization $organization = null): array
    {
        $name = SocialSecurityAdminNameCleaner::repairMojibake($name);
        $name = Str::lower(Str::ascii($name));
        $countryTokens = $organization
            ? collect(explode(' ', preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $organization->country))) ?? ''))
                ->filter()
                ->push(Str::lower((string) $organization->country_iso))
                ->values()
                ->all()
            : [];

        return collect($this->domainNameTokens($name))
            ->reject(fn (string $token) => in_array($token, ['the'], true))
            ->reject(fn (string $token) => in_array($token, $countryTokens, true))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function domainNameTokens(string $name): array
    {
        $name = SocialSecurityAdminNameCleaner::repairMojibake($name);
        $name = Str::lower(Str::ascii($name));
        $name = preg_replace('/\([^)]*\)/', ' ', $name) ?? $name;
        $name = str_replace('&', ' and ', $name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return collect(explode(' ', $name))
            ->map(fn (string $token) => trim($token))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function candidateTlds(string $countryIso): array
    {
        $iso = Str::lower($countryIso);
        $tlds = ['.com', '.org', '.net', '.bank'];

        if ($iso !== '' && strlen($iso) === 2) {
            array_splice($tlds, 1, 0, ['.' . $iso, '.com.' . $iso, '.co.' . $iso]);
        }

        return array_values(array_unique($tlds));
    }

    /**
     * @return array<int, string>
     */
    private function priorityTlds(string $countryIso): array
    {
        $iso = Str::lower($countryIso);

        if ($iso !== '' && strlen($iso) === 2) {
            return ['.' . $iso, '.com', '.com.' . $iso, '.co.' . $iso, '.net', '.org'];
        }

        return ['.com', '.net', '.org'];
    }

    /**
     * @return array<int, string>
     */
    private function bankDescriptors(): array
    {
        return [
            'bank', 'private', 'commercial', 'national', 'international', 'trust', 'savings',
            'credit', 'union', 'cooperative', 'capital', 'wealth', 'microfinance', 'finance',
            'banque', 'banco', 'banca',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function genericConfirmationTokens(): array
    {
        return array_values(array_unique(array_merge($this->bankDescriptors(), [
            'banking', 'financial', 'group', 'groupe', 'holding', 'company', 'corporation',
            'limited', 'public', 'plc', 'spa', 'sa', 'sarl', 'llc', 'inc', 'ltd',
        ])));
    }

    /**
     * @return array<int, string>
     */
    private function legalSuffixes(): array
    {
        return [
            'ab', 'ag', 'as', 'bv', 'co', 'corp', 'corporation', 'gmbh', 'inc', 'kg', 'limited',
            'llc', 'ltd', 'nv', 'plc', 'pty', 'sa', 'spa', 'srl', 'the',
        ];
    }

    private function blockedDomain(string $domain): bool
    {
        return Str::contains(Str::lower($domain), [
            'facebook.', 'linkedin.', 'twitter.', 'x.com', 'wikipedia.', 'youtube.', 'instagram.',
            'google.', 'bloomberg.', 'reuters.', 'crunchbase.', 'opencorporates.', 'bank-code.',
            'banksdaily.', 'thebanks.', 'wise.com', 'swift.com',
        ]);
    }

    private function bankCrawler(): MarketCrawler
    {
        return MarketCrawler::firstOrCreate([
            'crawler_key' => 'bank_domain_enrichment',
        ], [
            'name' => 'Bank domain enrichment crawler',
            'crawler_type' => 'bank_domain_crawler',
            'description' => 'Guesses and verifies official bank domains, then queues accepted bank sites for press and announcements crawling.',
            'is_enabled' => true,
        ]);
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
