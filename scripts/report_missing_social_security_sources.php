<?php

use App\Models\Country;
use App\Models\IntelligenceSource;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$configured = collect(config('country_intelligence.monitored_countries', []));
$dbCountries = Country::query()
    ->get()
    ->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));
$adminSources = IntelligenceSource::query()
    ->where('source_class', 'social_security_admin')
    ->get()
    ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso));

$missing = $configured
    ->filter(function (array $countryConfig, string $iso) use ($dbCountries, $adminSources) {
        $iso = strtoupper((string) ($countryConfig['iso_code'] ?? $iso));
        $dbCountry = $dbCountries->get($iso);

        return blank($dbCountry?->social_security_administration_name)
            && blank($countryConfig['social_security_administration_name'] ?? null)
            && ! $adminSources->has($iso);
    })
    ->map(function (array $countryConfig, string $iso) {
        return [
            'iso' => strtoupper((string) ($countryConfig['iso_code'] ?? $iso)),
            'region' => $countryConfig['region'] ?? '',
            'name' => $countryConfig['name'] ?? $iso,
        ];
    })
    ->values();

foreach ($missing as $row) {
    echo $row['iso'] . "\t" . $row['region'] . "\t" . $row['name'] . PHP_EOL;
}

echo 'Missing count: ' . $missing->count() . PHP_EOL;
