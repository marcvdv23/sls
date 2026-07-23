<?php

use App\Models\Country;
use App\Models\IntelligenceSource;
use App\Models\SocialSecurityAdminCandidate;
use App\Support\SocialSecurityAdminNameCleaner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$normaliseName = fn (string $name): string => Str::lower(Str::ascii(trim($name)));

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

$databaseCountries = Country::with('topics')
    ->get()
    ->keyBy(fn (Country $country) => strtoupper((string) $country->iso_code));

$intelligenceSources = IntelligenceSource::query()
    ->where('source_class', 'social_security_admin')
    ->get();

$sourceAdminNames = $intelligenceSources
    ->groupBy(fn (IntelligenceSource $source) => strtoupper((string) $source->country_iso))
    ->map(fn (Collection $sources) => $sources->pluck('name')->filter()->unique()->values());

$candidates = SocialSecurityAdminCandidate::query()
    ->with(['sourceDocument.chunks', 'marketOrganization'])
    ->where('status', '<>', 'rejected')
    ->whereNotNull('country_iso')
    ->whereNotNull('organization_name')
    ->orderBy('organization_name')
    ->get();

$extractedAdminNames = $candidates
    ->groupBy(fn (SocialSecurityAdminCandidate $candidate) => strtoupper((string) $candidate->country_iso))
    ->map(fn (Collection $candidates) => $candidates->pluck('organization_name')->filter()->unique()->values());

$urlIndex = [];

$addUrl = function (string $iso, string $name, string $kind, ?string $url) use (&$urlIndex, $normaliseName, $reviewedNameReplacements): void {
    $url = trim((string) $url);

    if ($url === '') {
        return;
    }

    $iso = strtoupper($iso);
    $names = [$name];

    if (isset($reviewedNameReplacements[$iso][$name])) {
        $names[] = $reviewedNameReplacements[$iso][$name];
    }

    foreach ($names as $indexedName) {
        $key = $normaliseName($indexedName);
        $urlIndex[$iso][$key][$kind][] = $url;
    }
};

$extractUrlsFromText = function (?string $text): array {
    $text = trim((string) $text);

    if ($text === '') {
        return [];
    }

    preg_match_all('/\bhttps?:\/\/[^\s<>"\']+/i', $text, $matches);

    return collect($matches[0] ?? [])
        ->map(fn (string $url) => trim($url, " \t\n\r\0\x0B.,;:)]}>\"'"))
        ->filter()
        ->unique()
        ->values()
        ->all();
};

$decodePdfLiteralString = function (string $value): string {
    $value = preg_replace('/\\\\\r?\n/', '', $value) ?? $value;
    $value = preg_replace_callback(
        '/\\\\([0-7]{1,3})/',
        fn (array $match): string => chr(octdec($match[1])),
        $value
    ) ?? $value;

    return strtr($value, [
        '\\n' => "\n",
        '\\r' => "\r",
        '\\t' => "\t",
        '\\b' => "\b",
        '\\f' => "\f",
        '\\(' => '(',
        '\\)' => ')',
        '\\\\' => '\\',
    ]);
};

$decodePdfHexString = function (string $value): string {
    $hex = preg_replace('/\s+/', '', $value) ?? '';

    if ($hex === '') {
        return '';
    }

    if (strlen($hex) % 2 === 1) {
        $hex .= '0';
    }

    return hex2bin($hex) ?: '';
};

$extractPdfUriTargets = function (?string $pdfBody) use ($decodePdfLiteralString, $decodePdfHexString, $extractUrlsFromText): array {
    $pdfBody = (string) $pdfBody;

    if ($pdfBody === '') {
        return [];
    }

    $urls = collect();

    preg_match_all('/\/URI\s*\(((?:\\\\.|[^\\\\)])*)\)/s', $pdfBody, $literalMatches);
    foreach ($literalMatches[1] ?? [] as $rawUri) {
        $urls = $urls->merge($extractUrlsFromText($decodePdfLiteralString($rawUri)));
    }

    preg_match_all('/\/URI\s*<([0-9A-Fa-f\s]+)>/s', $pdfBody, $hexMatches);
    foreach ($hexMatches[1] ?? [] as $rawUri) {
        $urls = $urls->merge($extractUrlsFromText($decodePdfHexString($rawUri)));
    }

    return $urls
        ->map(fn (string $url) => trim($url))
        ->filter()
        ->unique()
        ->values()
        ->all();
};

$resolveStoredDocumentPath = function (?string $storagePath): ?string {
    $storagePath = trim((string) $storagePath);

    if ($storagePath === '') {
        return null;
    }

    $candidates = [
        Storage::path($storagePath),
        storage_path('app' . DIRECTORY_SEPARATOR . $storagePath),
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
};

$pdfParser = new PdfParser();
$embeddedDocumentUrlCache = [];
$hyperlinkedDocumentUrlCache = [];

$embeddedDocumentUrls = function ($sourceDocument) use (&$embeddedDocumentUrlCache, $extractUrlsFromText, $resolveStoredDocumentPath, $pdfParser): array {
    if (! $sourceDocument) {
        return [];
    }

    $documentId = (int) $sourceDocument->id;

    if (array_key_exists($documentId, $embeddedDocumentUrlCache)) {
        return $embeddedDocumentUrlCache[$documentId];
    }

    $urls = collect();

    foreach ($sourceDocument->chunks as $chunk) {
        $urls = $urls->merge($extractUrlsFromText($chunk->chunk_text));
    }

    $path = $resolveStoredDocumentPath($sourceDocument->storage_path);

    if ($path !== null) {
        $urls = $urls->merge($extractUrlsFromText((string) @file_get_contents($path)));

        if (Str::lower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
            try {
                $urls = $urls->merge($extractUrlsFromText($pdfParser->parseFile($path)->getText()));
            } catch (Throwable) {
                // Some PDFs cannot be parsed; chunk/raw scans above still catch many embedded links.
            }
        }
    }

    return $embeddedDocumentUrlCache[$documentId] = $urls
        ->map(fn (string $url) => trim($url))
        ->filter()
        ->unique()
        ->values()
        ->all();
};

$hyperlinkedDocumentUrls = function ($sourceDocument) use (&$hyperlinkedDocumentUrlCache, $extractPdfUriTargets, $resolveStoredDocumentPath): array {
    if (! $sourceDocument) {
        return [];
    }

    $documentId = (int) $sourceDocument->id;

    if (array_key_exists($documentId, $hyperlinkedDocumentUrlCache)) {
        return $hyperlinkedDocumentUrlCache[$documentId];
    }

    $path = $resolveStoredDocumentPath($sourceDocument->storage_path);

    if ($path === null || Str::lower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
        return $hyperlinkedDocumentUrlCache[$documentId] = [];
    }

    return $hyperlinkedDocumentUrlCache[$documentId] = $extractPdfUriTargets((string) @file_get_contents($path));
};

foreach ($intelligenceSources as $source) {
    $addUrl((string) $source->country_iso, (string) $source->name, 'intelligence_source', $source->url);
}

foreach ($candidates as $candidate) {
    $addUrl((string) $candidate->country_iso, (string) $candidate->organization_name, 'source_document', $candidate->sourceDocument?->source_url);
    $addUrl((string) $candidate->country_iso, (string) $candidate->organization_name, 'market_organization', $candidate->marketOrganization?->website_url);

    foreach ($embeddedDocumentUrls($candidate->sourceDocument) as $url) {
        $addUrl((string) $candidate->country_iso, (string) $candidate->organization_name, 'source_document_embedded', $url);
    }

    foreach ($hyperlinkedDocumentUrls($candidate->sourceDocument) as $url) {
        $addUrl((string) $candidate->country_iso, (string) $candidate->organization_name, 'source_document_hyperlink', $url);
    }
}

$trackedCountries = collect(config('country_intelligence.monitored_countries', []))
    ->map(function (array $countryConfig, string $iso) use (
        $databaseCountries,
        $sourceAdminNames,
        $extractedAdminNames,
        $reviewedDuplicateNames,
        $reviewedInvalidNames,
        $reviewedNameReplacements
    ) {
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

$fileName = 'tracked-countries-source-entity-url-audit-' . now('America/Chicago')->format('Ymd-His') . '.csv';
$path = $directory . DIRECTORY_SEPARATOR . $fileName;

$handle = fopen($path, 'wb');
fwrite($handle, "\xEF\xBB\xBF");

fputcsv($handle, [
    'Country Order',
    'Country',
    'ISO',
    'Region',
    'Language',
    'Entity Order',
    'Organization',
    'Has URL',
    'Intelligence Source URLs',
    'Candidate Source Document URLs',
    'Source Document Embedded URLs',
    'Source Document Hyperlink URLs',
    'Market Organization URLs',
]);

$rowCount = 0;
$rowsWithUrls = 0;

foreach ($trackedCountries as $countryIndex => $country) {
    foreach ($country->social_security_administration_names as $entityIndex => $organizationName) {
        $urls = $urlIndex[$country->iso_code][$normaliseName($organizationName)] ?? [];
        $sourceUrls = collect($urls['intelligence_source'] ?? [])->unique()->values();
        $documentUrls = collect($urls['source_document'] ?? [])->unique()->values();
        $embeddedDocumentUrls = collect($urls['source_document_embedded'] ?? [])->unique()->values();
        $hyperlinkedDocumentUrls = collect($urls['source_document_hyperlink'] ?? [])->unique()->values();
        $marketUrls = collect($urls['market_organization'] ?? [])->unique()->values();
        $hasUrl = $sourceUrls->isNotEmpty()
            || $documentUrls->isNotEmpty()
            || $embeddedDocumentUrls->isNotEmpty()
            || $hyperlinkedDocumentUrls->isNotEmpty()
            || $marketUrls->isNotEmpty();

        if ($hasUrl) {
            $rowsWithUrls++;
        }

        fputcsv($handle, [
            $countryIndex + 1,
            $country->name,
            $country->iso_code,
            $country->region,
            $country->default_language_code,
            $entityIndex + 1,
            $organizationName,
            $hasUrl ? 'yes' : '',
            $sourceUrls->implode(' | '),
            $documentUrls->implode(' | '),
            $embeddedDocumentUrls->implode(' | '),
            $hyperlinkedDocumentUrls->implode(' | '),
            $marketUrls->implode(' | '),
        ]);

        $rowCount++;
    }
}

fclose($handle);

echo $fileName . PHP_EOL;
echo $trackedCountries->count() . ' tracked countries scanned.' . PHP_EOL;
echo $rowCount . ' tracked-country entity rows audited.' . PHP_EOL;
echo $rowsWithUrls . ' rows have at least one URL.' . PHP_EOL;
