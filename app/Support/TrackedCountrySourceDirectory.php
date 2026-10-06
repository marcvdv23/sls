<?php

namespace App\Support;

use App\Models\Country;
use App\Models\IntelligenceSource;
use App\Models\MarketOrganization;
use App\Models\Product;
use App\Models\SocialSecurityAdminCandidate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TrackedCountrySourceDirectory
{
    /**
     * @return Collection<int, object>
     */
    public function countries(?Product $defaultProduct = null): Collection
    {
        $defaultProduct ??= Product::query()
            ->where('code', SlsSettings::get('products.default_code', config('sls.products.default_code', 'SSAS')))
            ->orWhere('name', SlsSettings::get('products.default_name', config('sls.products.default_name', 'Interact SSAS')))
            ->first()
            ?: Product::query()->orderBy('name')->first();

        $databaseCountries = Country::with('topics')->get()->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));
        $sourceAdminRecords = IntelligenceSource::query()
            ->where('source_class', 'social_security_admin')
            ->get()
            ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso));
        $adminCandidates = SocialSecurityAdminCandidate::query()
            ->with(['sourceDocument', 'marketOrganization'])
            ->where('status', '<>', 'rejected')
            ->whereNotNull('country_iso')
            ->whereNotNull('organization_name')
            ->orderBy('organization_name')
            ->get()
            ->groupBy(fn (SocialSecurityAdminCandidate $candidate) => strtoupper((string) $candidate->country_iso));
        $manualAdminOrganizations = MarketOrganization::query()
            ->where('organization_subcategory', 'social_security_administration')
            ->whereNotNull('country_iso')
            ->get()
            ->groupBy(fn (MarketOrganization $organization) => strtoupper((string) $organization->country_iso));
        $manualAdminNameSuppressions = Schema::hasTable('social_security_admin_name_suppressions')
            ? DB::table('social_security_admin_name_suppressions')
                ->get(['country_iso', 'name_normalized'])
                ->groupBy(fn ($suppression) => strtoupper((string) $suppression->country_iso))
                ->map(fn ($suppressions) => $suppressions->pluck('name_normalized')->filter()->values())
            : collect();

        return collect(config('country_intelligence.monitored_countries', []))
            ->map(function (array $countryConfig, string $iso) use (
                $adminCandidates,
                $databaseCountries,
                $defaultProduct,
                $manualAdminNameSuppressions,
                $manualAdminOrganizations,
                $sourceAdminRecords
            ) {
                $iso = strtoupper((string) ($countryConfig['iso_code'] ?? $iso));
                $databaseCountry = $databaseCountries->get($iso);
                $countryName = $databaseCountry?->name ?? $countryConfig['name'] ?? $iso;
                $names = collect([
                    $databaseCountry?->social_security_administration_name,
                    $countryConfig['social_security_administration_name'] ?? null,
                ])
                    ->merge($sourceAdminRecords->get($iso, collect())->pluck('name'))
                    ->merge($adminCandidates->get($iso, collect())->pluck('organization_name'))
                    ->merge($manualAdminOrganizations->get($iso, collect())->pluck('name'))
                    ->filter()
                    ->flatMap(fn (string $name) => preg_split('/\s+\/\s+/', $name) ?: [])
                    ->map(fn (string $name) => SocialSecurityAdminNameCleaner::repairMojibake(trim($name)))
                    ->filter();

                $names = SocialSecurityAdminNameCleaner::canonicalizeList(
                    $names,
                    $countryName,
                    $iso,
                    $countryConfig['search_names'] ?? [],
                )
                    ->reject(fn (string $name) => $this->isSuppressed($iso, $name, $manualAdminNameSuppressions))
                    ->unique(fn (string $name) => $this->normalizeName($name))
                    ->sort()
                    ->values();

                $links = $names
                    ->map(fn (string $name) => $this->linkForName(
                        $iso,
                        $name,
                        $sourceAdminRecords->get($iso, collect()),
                        $adminCandidates->get($iso, collect()),
                        $manualAdminOrganizations->get($iso, collect()),
                        $defaultProduct,
                    ))
                    ->values();

                return (object) [
                    'name' => $countryName,
                    'iso_code' => $iso,
                    'region' => $databaseCountry?->region ?? $countryConfig['region'] ?? 'Not assigned',
                    'default_language_code' => $databaseCountry?->default_language_code ?? $countryConfig['default_language_code'] ?? 'en',
                    'social_security_administration_names' => $names,
                    'social_security_administration_links' => $links,
                    'social_protection_profile_url' => $databaseCountry?->social_protection_profile_url
                        ?: 'https://www.social-protection.org/gimi/gess/ShowCountryProfile.action?iso=' . $iso,
                    'social_protection_profile_checked_at' => $databaseCountry?->social_protection_profile_checked_at,
                    'social_protection_profile_last_error' => $databaseCountry?->social_protection_profile_last_error,
                    'topics' => $databaseCountry?->topics ?? collect(),
                ];
            })
            ->sortBy([['region', 'asc'], ['name', 'asc']])
            ->values();
    }

    private function linkForName(
        string $iso,
        string $name,
        Collection $sourceRecords,
        Collection $candidateRecords,
        Collection $manualOrganizations,
        ?Product $defaultProduct,
    ): array {
        $key = $this->normalizeName($name);
        $manual = $manualOrganizations
            ->first(fn (MarketOrganization $organization) => $this->normalizeName((string) $organization->name) === $key);
        $source = $sourceRecords
            ->first(fn (IntelligenceSource $source) => $this->normalizeName((string) $source->name) === $key);
        $candidate = $candidateRecords
            ->first(fn (SocialSecurityAdminCandidate $candidate) => $this->normalizeName((string) $candidate->organization_name) === $key);
        $generalUrl = $manual?->website_url
            ?: $source?->url
            ?: $candidate?->marketOrganization?->website_url
            ?: $candidate?->sourceDocument?->source_url;

        return [
            'name' => $name,
            'url' => $generalUrl,
            'general_url' => $generalUrl,
            'press_url' => $manual?->news_page_url ?: $candidate?->marketOrganization?->news_page_url,
            'tenders_url' => $manual?->procurement_page_url ?: $candidate?->marketOrganization?->procurement_page_url,
            'product_id' => $manual?->product_id ?: $defaultProduct?->id,
            'country_iso' => $iso,
        ];
    }

    private function isSuppressed(string $iso, string $name, Collection $suppressions): bool
    {
        $suppressed = $suppressions->get($iso, collect());

        return $suppressed->contains($this->normalizeName($name));
    }

    private function normalizeName(string $name): string
    {
        return Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }
}
