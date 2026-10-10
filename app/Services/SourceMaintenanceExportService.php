<?php

namespace App\Services;

use App\Models\Product;
use App\Support\TrackedCountrySourceDirectory;
use Illuminate\Support\Collection;

class SourceMaintenanceExportService
{
    public function __construct(private readonly TrackedCountrySourceDirectory $directory)
    {
    }

    /**
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function rows(Product $product, string $mode = 'missing'): array
    {
        $mode = in_array($mode, ['all', 'missing', 'missing_core', 'missing_auxiliary'], true)
            ? $mode
            : 'missing_core';
        $headers = [
            'country_iso',
            'country',
            'region',
            'source_category',
            'source_label',
            'organization_name',
            'general_url',
            'press_url',
            'tenders_url',
            'organization_nonexistent',
            'general_url_nonexistent',
            'press_url_nonexistent',
            'tenders_url_nonexistent',
            'missing_organization',
            'missing_general_url',
            'missing_press_url',
            'missing_tenders_url',
            'organization_nonexistent_confirmed_at',
            'general_url_nonexistent_confirmed_at',
            'press_url_nonexistent_confirmed_at',
            'tenders_url_nonexistent_confirmed_at',
        ];

        $rows = $this->directory->countries($product)
            ->flatMap(fn (object $country) => $this->countryRows($country, $mode))
            ->values()
            ->all();

        return [
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array<int, string>>
     */
    private function countryRows(object $country, string $mode): Collection
    {
        return collect($country->social_security_administration_links ?? [])
            ->map(function (array $slot) use ($country) {
                $organizationNonexistentAt = (string) ($slot['organization_nonexistent_confirmed_at'] ?? '');
                $generalNonexistentAt = (string) ($slot['general_url_nonexistent_confirmed_at'] ?? '');
                $pressNonexistentAt = (string) ($slot['press_url_nonexistent_confirmed_at'] ?? '');
                $tendersNonexistentAt = (string) ($slot['tenders_url_nonexistent_confirmed_at'] ?? '');

                $missingOrganization = blank($slot['name'] ?? null) && blank($organizationNonexistentAt);
                $missingGeneral = blank($slot['general_url'] ?? null) && blank($generalNonexistentAt);
                $missingPress = blank($slot['press_url'] ?? null) && blank($pressNonexistentAt);
                $missingTenders = blank($slot['tenders_url'] ?? null) && blank($tendersNonexistentAt);

                return [
                    'row' => [
                        strtoupper((string) ($country->iso_code ?? '')),
                        (string) ($country->name ?? ''),
                        (string) ($country->region ?? ''),
                        (string) ($slot['source_category'] ?? ''),
                        (string) ($slot['slot_label'] ?? ''),
                        (string) ($slot['name'] ?? ''),
                        (string) ($slot['general_url'] ?? ''),
                        (string) ($slot['press_url'] ?? ''),
                        (string) ($slot['tenders_url'] ?? ''),
                        filled($organizationNonexistentAt) ? 'yes' : 'no',
                        filled($generalNonexistentAt) ? 'yes' : 'no',
                        filled($pressNonexistentAt) ? 'yes' : 'no',
                        filled($tendersNonexistentAt) ? 'yes' : 'no',
                        $missingOrganization ? 'yes' : 'no',
                        $missingGeneral ? 'yes' : 'no',
                        $missingPress ? 'yes' : 'no',
                        $missingTenders ? 'yes' : 'no',
                        $organizationNonexistentAt,
                        $generalNonexistentAt,
                        $pressNonexistentAt,
                        $tendersNonexistentAt,
                    ],
                    'is_missing' => $missingOrganization || $missingGeneral || $missingPress || $missingTenders,
                    'is_missing_core' => $missingOrganization || $missingGeneral,
                    'is_missing_auxiliary' => ! $missingOrganization && ! $missingGeneral && ($missingPress || $missingTenders),
                ];
            })
            ->filter(fn (array $item) => match ($mode) {
                'all' => true,
                'missing_core' => $item['is_missing_core'],
                'missing_auxiliary' => $item['is_missing_auxiliary'],
                default => $item['is_missing'],
            })
            ->map(fn (array $item) => $item['row'])
            ->values();
    }
}
