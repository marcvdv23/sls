<?php

namespace App\Services;

use App\Models\Country;
use App\Models\MarketCrawler;
use App\Models\MarketOrganization;
use App\Models\Product;
use App\Support\SocialSecurityAdminNameCleaner;
use App\Support\TrackedCountrySourceDirectory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SourceMaintenanceImportService
{
    public function import(string $path, Product $product, bool $dryRun = false, bool $allowOverwrite = false): array
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException('CSV not found: ' . $path);
        }

        $handle = fopen($path, 'rb');
        if (! $handle) {
            throw new InvalidArgumentException('Could not open CSV: ' . $path);
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);

            throw new InvalidArgumentException('CSV is empty.');
        }

        $headers = array_map(
            fn ($header) => Str::of((string) $header)->lower()->trim()->replace(' ', '_')->toString(),
            $headers,
        );

        foreach (['country_iso', 'source_category'] as $column) {
            if (! in_array($column, $headers, true)) {
                fclose($handle);

                throw new InvalidArgumentException('Missing required CSV column: ' . $column);
            }
        }

        $slots = TrackedCountrySourceDirectory::sourceOrganizationSlotsBySubcategory();
        $crawler = MarketCrawler::firstOrCreate(
            ['crawler_key' => 'social_security_organization'],
            [
                'name' => 'Social security organization crawler',
                'crawler_type' => 'social_security_contact_crawler',
                'description' => 'Finds official social security organization websites, media/press pages, procurement pages, leadership, and public contacts.',
                'is_enabled' => true,
            ],
        );

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $lineNumber = 1;
        $messages = [];

        while (($values = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $row = array_combine($headers, array_pad($values, count($headers), ''));
            if (! is_array($row)) {
                $skipped++;
                $messages[] = "Skipping line {$lineNumber}: could not read row.";
                continue;
            }

            $iso = Str::upper(trim((string) ($row['country_iso'] ?? '')));
            $sourceCategory = trim((string) ($row['source_category'] ?? ''));
            if ($iso === '' || ! array_key_exists($sourceCategory, $slots)) {
                $skipped++;
                $messages[] = "Skipping line {$lineNumber}: invalid country_iso or source_category.";
                continue;
            }

            $country = Country::query()->where('iso_code', $iso)->first();
            $countryName = trim((string) ($row['country'] ?? '')) ?: ($country?->name ?: $iso);
            $region = trim((string) ($row['region'] ?? '')) ?: $country?->region;
            $organizationName = SocialSecurityAdminNameCleaner::repairMojibake(trim((string) ($row['organization_name'] ?? '')));
            $organizationNonexistent = $this->truthy($row['organization_nonexistent'] ?? false);
            $generalNonexistent = $this->truthy($row['general_url_nonexistent'] ?? false);
            $pressNonexistent = $this->truthy($row['press_url_nonexistent'] ?? false);
            $tendersNonexistent = $this->truthy($row['tenders_url_nonexistent'] ?? false);
            $generalUrl = $this->normalizeUrl($row['general_url'] ?? null);
            $pressUrl = $this->normalizeUrl($row['press_url'] ?? null);
            $tendersUrl = $this->normalizeUrl($row['tenders_url'] ?? null);

            foreach ([
                'general_url' => [$row['general_url'] ?? null, $generalUrl],
                'press_url' => [$row['press_url'] ?? null, $pressUrl],
                'tenders_url' => [$row['tenders_url'] ?? null, $tendersUrl],
            ] as $column => [$rawUrl, $normalizedUrl]) {
                if (filled(trim((string) $rawUrl)) && $normalizedUrl === null) {
                    $skipped++;
                    $messages[] = "Skipping line {$lineNumber}: invalid {$column}.";
                    continue 2;
                }
            }

            $organization = MarketOrganization::query()
                ->where('country_iso', $iso)
                ->where('organization_subcategory', $sourceCategory)
                ->when(Schema::hasColumn('market_organizations', 'product_id'), fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('product_id', $product->id)
                    ->orWhereNull('product_id')))
                ->first();

            $isNew = ! $organization;
            $organization ??= new MarketOrganization([
                'source_fingerprint' => hash('sha256', 'manual-source-maintenance-import|' . $product->id . '|' . $iso . '|' . $sourceCategory),
            ]);

            $changes = [];
            if ($isNew) {
                $changes = [
                    'market_crawler_id' => $crawler->id,
                    'organization_type' => 'government_agency',
                    'industry' => 'government',
                    'organization_subcategory' => $sourceCategory,
                    'country' => $countryName,
                    'country_raw' => $countryName,
                    'country_iso' => $iso,
                    'country_resolution_status' => 'resolved',
                    'region' => $region,
                    'status' => 'active',
                    'lead_status' => 'researching',
                    'lead_source' => 'manual_social_security_admin_url',
                    'last_crawler_name' => $crawler->name,
                ];

                if (Schema::hasColumn('market_organizations', 'product_id')) {
                    $changes['product_id'] = $product->id;
                }
            }

            $canSetOrganization = $allowOverwrite || (blank($organization->name) && blank($organization->organization_nonexistent_confirmed_at));
            if ($canSetOrganization && $organizationName !== '') {
                $changes['name'] = Str::limit($organizationName, 255, '');
                $changes['name_normalized'] = $this->normalizeName($organizationName);
                $changes['organization_nonexistent_confirmed_at'] = null;
            } elseif ($canSetOrganization && $organizationNonexistent) {
                $label = $slots[$sourceCategory]['label'] ?? $sourceCategory;
                $changes['name'] = 'Confirmed non-existence - ' . $label;
                $changes['name_normalized'] = $this->normalizeName($changes['name']);
                $changes['organization_nonexistent_confirmed_at'] = now();
            }

            foreach ([
                ['website_url', 'website_url_nonexistent_confirmed_at', $generalUrl, $generalNonexistent],
                ['news_page_url', 'news_page_url_nonexistent_confirmed_at', $pressUrl, $pressNonexistent],
                ['procurement_page_url', 'procurement_page_url_nonexistent_confirmed_at', $tendersUrl, $tendersNonexistent],
            ] as [$urlColumn, $nonexistentColumn, $url, $nonexistent]) {
                $canSetUrl = $allowOverwrite || (blank($organization->{$urlColumn}) && blank($organization->{$nonexistentColumn}));
                if (! $canSetUrl) {
                    continue;
                }

                if ($url !== null) {
                    $changes[$urlColumn] = $url;
                    $changes[$nonexistentColumn] = null;
                } elseif ($nonexistent) {
                    $changes[$urlColumn] = null;
                    $changes[$nonexistentColumn] = now();
                }
            }

            if (array_key_exists('website_url', $changes)) {
                $changes['website_domain'] = $this->domainFromUrl($changes['website_url']);
            }

            $dirtyChanges = collect($changes)
                ->reject(fn ($value, $key) => ! $isNew && (string) ($organization->{$key} ?? '') === (string) ($value ?? ''))
                ->all();

            if ($dirtyChanges === []) {
                $skipped++;
                continue;
            }

            $action = $isNew ? 'create' : 'update';
            $verb = $dryRun ? 'Would ' . $action : ($isNew ? 'Created' : 'Updated');
            $messages[] = "{$verb} {$iso} {$sourceCategory}: " . implode(', ', array_keys($dirtyChanges));

            if (! $dryRun) {
                $organization->fill($changes)->save();
            }

            $isNew ? $created++ : $updated++;
        }

        fclose($handle);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'overwrite' => $allowOverwrite,
            'messages' => $messages,
        ];
    }

    private function normalizeName(string $name): string
    {
        return Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (! Str::startsWith(Str::lower($url), ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function domainFromUrl(?string $url): ?string
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host ? Str::of($host)->lower()->replaceStart('www.', '')->toString() : null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array(Str::lower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }
}
