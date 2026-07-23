<?php

use App\Models\Country;
use App\Models\IntelligenceSource;
use App\Models\SocialSecurityAdminCandidate;
use App\Support\SocialSecurityAdminNameCleaner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$databaseCountries = Country::with('topics')
    ->get()
    ->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));

$sourceAdminNames = IntelligenceSource::query()
    ->where('source_class', 'social_security_admin')
    ->get()
    ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso))
    ->map(fn ($sources) => $sources->pluck('name')->filter()->unique()->values());

$extractedAdminNames = SocialSecurityAdminCandidate::query()
    ->where('status', '<>', 'rejected')
    ->whereNotNull('country_iso')
    ->whereNotNull('organization_name')
    ->orderBy('organization_name')
    ->get()
    ->groupBy(fn (SocialSecurityAdminCandidate $candidate) => strtoupper((string) $candidate->country_iso))
    ->map(fn ($candidates) => $candidates->pluck('organization_name')->filter()->unique()->values());

$reviewedDuplicateNames = [
    'AO' => ['National Institute of Social Security'],
    'BS' => ['National Insurance Board of The'],
    'BB' => ['National Insurance Office'],
    'BR' => ['INSS'],
    'CL' => ['Social Security Institute'],
    'CR' => ['Costa Rican Social Insurance Fund'],
    'CY' => ['Welfare Benefits Administration Service'],
    'EC' => ['Ecuadorian Social Security Institute'],
    'ET' => ['Public Servants Social Security Service'],
    'GE' => ['Social Service Agency'],
    'GT' => ['Social Security Institute'],
    'HN' => ['Social Security Institute'],
    'LK' => ['Ministry of Labour'],
    'LT' => ['Ministry of Health of the Republic'],
    'ME' => ['Insurance'],
    'MX' => ['Mexican Social Security Institute'],
    'MZ' => ['National Institute of Social Security'],
    'PA' => ['Social Insurance Fund', 'Caja de Seguro Social Panamá'],
    'PY' => ['Social Insurance Institute'],
    'PE' => ['Social Security Normalization', 'ONP'],
    'KR' => ['National Pension Service Korea'],
    'ZA' => ['South African Social Security Agency'],
    'SI' => ['Ministry of Labor, Family, Social Affairs, and Equal Opportunities'],
    'TW' => ['Department of Labor Insurance of the Ministry of Labor'],
    'UY' => ['National Insurance Bank'],
    'VE' => ['Venezuelan Social Insurance Institute'],
    'VN' => ['Social Security'],
    'AR' => ['ANSES'],
];

$reviewedInvalidNames = [
    'BO' => ['Gestora Pública'],
    'CV' => ['National Health Service Ministry of Family, Inclusion and Social Development'],
    'JP' => ['National Pension Programme, Employees\' Pension Insurance Programme Japan Pension Service'],
    'MX' => ['México'],
    'UZ' => ['Ministry of Economy and Finance. Citizens\' Commissions'],
];

$reviewedNameReplacements = [
    'BO' => [
        'de la Seguridad Social de Largo Plazo' => 'Gestora Pública de la Seguridad Social de Largo Plazo',
    ],
    'CA' => [
        'WorkplaceNL' => 'Workplace Health, Safety and Compensation Commission of Newfoundland and Labrador (WorkplaceNL)',
    ],
    'DK' => [
        'Payment' => 'Payment Denmark',
    ],
    'VG' => [
        'Social Security Board' => 'BVI Social Security Board',
    ],
    'SV' => [
        'Salvadorian Social Insurance Institute' => 'Instituto Salvadoreno del Seguro Social',
    ],
    'UZ' => [
        'National Federation of Trade Unions)' => 'National Federation of Trade Unions',
    ],
];

$trackedCountries = collect(config('country_intelligence.monitored_countries', []))
    ->map(function (array $countryConfig, string $iso) use ($databaseCountries, $sourceAdminNames, $extractedAdminNames, $reviewedDuplicateNames, $reviewedInvalidNames, $reviewedNameReplacements) {
        $iso = strtoupper((string) ($countryConfig['iso_code'] ?? $iso));
        $databaseCountry = $databaseCountries->get($iso);
        $adminNames = $extractedAdminNames->get($iso, collect());
        $previousAdminNames = collect([
            $databaseCountry?->social_security_administration_name,
            $countryConfig['social_security_administration_name'] ?? null,
        ])
            ->merge($sourceAdminNames->get($iso, collect()))
            ->filter()
            ->flatMap(fn (string $name) => preg_split('/\s+\/\s+/', $name) ?: [])
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();
        $countryName = $databaseCountry?->name ?? $countryConfig['name'] ?? $iso;
        $adminNames = SocialSecurityAdminNameCleaner::canonicalizeList(
            $adminNames->merge($previousAdminNames),
            $countryName,
            $iso,
            $countryConfig['search_names'] ?? [],
        );
        $reviewedRemovals = collect($reviewedDuplicateNames[$iso] ?? [])
            ->merge($reviewedInvalidNames[$iso] ?? [])
            ->all();
        $reviewedReplacements = $reviewedNameReplacements[$iso] ?? [];
        $adminNames = $adminNames
            ->map(fn (string $name) => $reviewedReplacements[$name] ?? $name)
            ->reject(fn (string $name) => in_array($name, $reviewedRemovals, true))
            ->unique(fn (string $name) => Str::lower(Str::ascii($name)))
            ->values();

        return (object) [
            'name' => $countryName,
            'iso_code' => $iso,
            'region' => $databaseCountry?->region ?? $countryConfig['region'] ?? 'Not assigned',
            'default_language_code' => $databaseCountry?->default_language_code ?? $countryConfig['default_language_code'] ?? 'en',
            'social_security_administration_names' => $adminNames,
        ];
    })
    ->sortBy([['region', 'asc'], ['name', 'asc']])
    ->values();

$directory = public_path('sls-exports');
if (! is_dir($directory)) {
    mkdir($directory, 0775, true);
}

$fileName = 'tracked-countries-source-entities-' . now('America/Chicago')->format('Ymd-His') . '.csv';
$path = $directory . DIRECTORY_SEPARATOR . $fileName;

$handle = fopen($path, 'wb');
fwrite($handle, "\xEF\xBB\xBF");

fputcsv($handle, [
    'Remove?',
    'Country Order',
    'Country',
    'ISO',
    'Region',
    'Language',
    'Entity Order',
    'Organization',
]);

$rowCount = 0;

foreach ($trackedCountries as $countryIndex => $country) {
    foreach ($country->social_security_administration_names as $entityIndex => $organizationName) {
        fputcsv($handle, [
            '',
            $countryIndex + 1,
            $country->name,
            $country->iso_code,
            $country->region,
            $country->default_language_code,
            $entityIndex + 1,
            $organizationName,
        ]);

        $rowCount++;
    }
}

fclose($handle);

echo $fileName . PHP_EOL;
echo $trackedCountries->count() . ' tracked countries scanned.' . PHP_EOL;
echo $rowCount . ' tracked-country entity rows exported.' . PHP_EOL;
