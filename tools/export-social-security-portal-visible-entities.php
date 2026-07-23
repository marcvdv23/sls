<?php

use App\Models\SocialSecurityAdminCandidate;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$candidates = SocialSecurityAdminCandidate::query()
    ->with(['country', 'sourceDocument'])
    ->withCount('contexts')
    ->latest()
    ->limit(300)
    ->get();

$directory = public_path('sls-exports');
if (! is_dir($directory)) {
    mkdir($directory, 0775, true);
}

$fileName = 'social-security-portal-visible-entities-' . now('America/Chicago')->format('Ymd-His') . '.csv';
$path = $directory . DIRECTORY_SEPARATOR . $fileName;

$handle = fopen($path, 'wb');
fwrite($handle, "\xEF\xBB\xBF");

fputcsv($handle, [
    'Remove?',
    'Candidate ID',
    'Status',
    'Country',
    'ISO',
    'Region',
    'Language',
    'Organization',
    'Context Count',
    'Market Organization ID',
    'Source Document ID',
    'Source Document',
]);

foreach ($candidates as $candidate) {
    $country = $candidate->country;

    fputcsv($handle, [
        '',
        $candidate->id,
        $candidate->status,
        $country?->name ?? $candidate->country_name,
        $country?->iso_code ?? $candidate->country_iso,
        $country?->region,
        $country?->default_language_code,
        $candidate->organization_name,
        $candidate->contexts_count,
        $candidate->market_organization_id,
        $candidate->source_document_id,
        $candidate->sourceDocument?->title,
    ]);
}

fclose($handle);

echo $fileName . PHP_EOL;
echo $candidates->count() . ' portal-visible entity rows exported.' . PHP_EOL;
