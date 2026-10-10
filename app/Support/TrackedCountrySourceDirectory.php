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
    public const SOURCE_ORGANIZATION_SLOTS = [
        [
            'key' => 'social_security',
            'label' => 'Social security',
            'subcategory' => 'social_security_administration',
            'description' => 'Main social security administration or fund.',
        ],
        [
            'key' => 'ministry_social_security',
            'label' => 'Ministry of Social Security',
            'subcategory' => 'ministry_social_security',
            'description' => 'Ministry responsible for social security, social protection, welfare, or social affairs.',
        ],
        [
            'key' => 'pensions_civil_service',
            'label' => 'Pensions - civil service',
            'subcategory' => 'pensions_civil_service',
            'description' => 'Civil-service pension scheme, fund, or administrator.',
        ],
        [
            'key' => 'pensions_military',
            'label' => 'Pensions - military',
            'subcategory' => 'pensions_military',
            'description' => 'Military, police, defence, or veterans pension administration.',
        ],
        [
            'key' => 'pensions_private_sector',
            'label' => 'Pensions - private sector',
            'subcategory' => 'pensions_private_sector',
            'description' => 'Private-sector, national insurance, provident fund, or pension administrator.',
        ],
        [
            'key' => 'employment_injury',
            'label' => 'Employment injury',
            'subcategory' => 'employment_injury',
            'description' => 'Employment injury, workers compensation, or occupational accident insurance.',
        ],
        [
            'key' => 'ministry_labor',
            'label' => 'Ministry of Labor',
            'subcategory' => 'ministry_labor',
            'description' => 'Labor, employment, manpower, or labour affairs ministry.',
        ],
        [
            'key' => 'ministry_finance',
            'label' => 'Ministry of Finance',
            'subcategory' => 'ministry_finance',
            'description' => 'Finance, treasury, economy, or budget ministry.',
        ],
        [
            'key' => 'ministry_civil_service',
            'label' => 'Ministry of Civil Service',
            'subcategory' => 'ministry_civil_service',
            'description' => 'Civil service, public service, public administration, or government workforce ministry.',
        ],
    ];

    /**
     * @return array<string, array<string, string>>
     */
    public static function sourceOrganizationSlotsBySubcategory(): array
    {
        return collect(self::SOURCE_ORGANIZATION_SLOTS)
            ->keyBy('subcategory')
            ->all();
    }

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
        $sourceOrganizationSubcategories = collect(self::SOURCE_ORGANIZATION_SLOTS)
            ->pluck('subcategory')
            ->push('social_security_administration')
            ->unique()
            ->values();
        $manualSourceOrganizations = MarketOrganization::query()
            ->whereIn('organization_subcategory', $sourceOrganizationSubcategories->all())
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
                $manualSourceOrganizations,
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
                    ->merge($manualSourceOrganizations->get($iso, collect())->pluck('name'))
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

                $links = collect(self::SOURCE_ORGANIZATION_SLOTS)
                    ->map(fn (array $slot) => $this->linkForSlot(
                        $slot,
                        $iso,
                        $countryName,
                        $names,
                        $sourceAdminRecords->get($iso, collect()),
                        $adminCandidates->get($iso, collect()),
                        $manualSourceOrganizations->get($iso, collect()),
                        $defaultProduct,
                    ))
                    ->values();
                $linkedNameKeys = $links
                    ->pluck('name')
                    ->filter()
                    ->map(fn (string $name) => $this->normalizeName($name))
                    ->unique()
                    ->values();
                $unlinkedNames = $names
                    ->reject(fn (string $name) => $linkedNameKeys->contains($this->normalizeName($name)))
                    ->values();
                $missingSourceCount = $links
                    ->filter(fn (array $link) => blank($link['name'] ?? null))
                    ->count();

                return (object) [
                    'name' => $countryName,
                    'iso_code' => $iso,
                    'region' => $databaseCountry?->region ?? $countryConfig['region'] ?? 'Not assigned',
                    'default_language_code' => $databaseCountry?->default_language_code ?? $countryConfig['default_language_code'] ?? 'en',
                    'social_security_administration_names' => $names,
                    'social_security_administration_links' => $links,
                    'unlinked_source_organization_names' => $unlinkedNames,
                    'missing_source_count' => $missingSourceCount,
                    'complete_source_count' => $links->count() - $missingSourceCount,
                    'total_source_count' => $links->count(),
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

    /**
     * @param array<string, string> $slot
     * @param Collection<int, string> $adminNames
     */
    private function linkForSlot(
        array $slot,
        string $iso,
        string $countryName,
        Collection $adminNames,
        Collection $sourceRecords,
        Collection $candidateRecords,
        Collection $manualOrganizations,
        ?Product $defaultProduct,
    ): array {
        $subcategory = (string) $slot['subcategory'];
        $slotLabel = (string) $slot['label'];
        $slotKey = (string) $slot['key'];
        $slotNameKey = $this->normalizeName($slotLabel);
        $manual = $manualOrganizations
            ->first(fn (MarketOrganization $organization) => (string) $organization->organization_subcategory === $subcategory)
            ?: $manualOrganizations->first(fn (MarketOrganization $organization) => $this->normalizeName((string) $organization->name) === $slotNameKey)
            ?: $manualOrganizations->first(fn (MarketOrganization $organization) => $this->nameMatchesSourceSlot($slotKey, (string) $organization->name));

        $source = null;
        $candidate = null;
        $matchedName = $adminNames->first(fn (string $name) => $this->nameMatchesSourceSlot($slotKey, $name));
        $displayName = $manual?->name ?: ($matchedName ?: null);

        if ($subcategory === 'social_security_administration') {
            $preferredAdminName = $matchedName ?: $adminNames->first();

            if ($preferredAdminName) {
                $displayName = $manual?->name ?: $preferredAdminName;
                $preferredKey = $this->normalizeName((string) $preferredAdminName);
                $source = $sourceRecords
                    ->first(fn (IntelligenceSource $source) => $this->normalizeName((string) $source->name) === $preferredKey);
                $candidate = $candidateRecords
                    ->first(fn (SocialSecurityAdminCandidate $candidate) => $this->normalizeName((string) $candidate->organization_name) === $preferredKey);
            }

            $source ??= $sourceRecords->first();
            $candidate ??= $candidateRecords->first();
        } else {
            $displayName = $manual?->name ?: ($matchedName ?: null);
        }

        $displayName = filled($displayName)
            ? SocialSecurityAdminNameCleaner::repairMojibake(trim((string) $displayName))
            : null;

        $generalUrl = $manual?->website_url
            ?: $source?->url
            ?: $candidate?->marketOrganization?->website_url
            ?: $candidate?->sourceDocument?->source_url;

        return [
            'slot_key' => $slotKey,
            'slot_label' => $slotLabel,
            'source_category' => $subcategory,
            'description' => (string) ($slot['description'] ?? ''),
            'name' => $displayName,
            'is_defined' => filled($displayName),
            'url' => $generalUrl,
            'general_url' => $generalUrl,
            'press_url' => $manual?->news_page_url ?: $candidate?->marketOrganization?->news_page_url,
            'tenders_url' => $manual?->procurement_page_url ?: $candidate?->marketOrganization?->procurement_page_url,
            'product_id' => $manual?->product_id ?: $defaultProduct?->id,
            'country_iso' => $iso,
            'country_name' => $countryName,
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

    private function nameMatchesSourceSlot(string $slotKey, string $name): bool
    {
        $name = $this->normalizeName($name);

        return match ($slotKey) {
            'social_security' => Str::contains($name, [
                'social security',
                'social insurance',
                'national insurance',
                'provident fund',
                'pension fund',
                'pensions fund',
                'retirement',
                'caisse',
            ]) && ! Str::contains($name, ['ministry', 'department of labour', 'department of labor']),
            'pensions_civil_service' => Str::contains($name, ['civil service', 'public service', 'public officers']) && Str::contains($name, ['pension', 'retirement']),
            'pensions_military' => Str::contains($name, ['military', 'defence', 'defense', 'veteran', 'armed forces', 'police']) && Str::contains($name, ['pension', 'retirement']),
            'pensions_private_sector' => Str::contains($name, ['private sector', 'national insurance', 'provident', 'pension', 'retirement']),
            'employment_injury' => Str::contains($name, ['employment injury', 'workers compensation', 'workers compensation', 'occupational injury', 'work injury', 'accident insurance']),
            'ministry_social_security' => Str::contains($name, ['ministry', 'department']) && Str::contains($name, ['social security', 'social protection', 'social affairs', 'social welfare', 'welfare']),
            'ministry_labor' => Str::contains($name, ['ministry']) && Str::contains($name, ['labor', 'labour', 'employment', 'manpower']),
            'ministry_finance' => Str::contains($name, ['ministry']) && Str::contains($name, ['finance', 'treasury', 'economy', 'budget']),
            'ministry_civil_service' => Str::contains($name, ['ministry', 'department']) && Str::contains($name, ['civil service', 'public service', 'public administration']),
            default => false,
        };
    }
}
