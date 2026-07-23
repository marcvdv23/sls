<?php

use App\Models\SocialSecurityAdminCandidate;
use Illuminate\Contracts\Console\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$directory = public_path('sls-exports');

if (! is_dir($directory)) {
    mkdir($directory, 0775, true);
}

$fileName = 'social-security-entities-flat-' . now('America/Chicago')->format('Ymd-His') . '.csv';
$path = $directory . DIRECTORY_SEPARATOR . $fileName;
$out = fopen($path, 'w');

fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Remove?',
    'Candidate ID',
    'Status',
    'Country',
    'ISO',
    'Region',
    'Language',
    'Organization',
    'Market Organization ID',
    'Source Document ID',
    'Source Document',
]);

SocialSecurityAdminCandidate::query()
    ->with(['country', 'sourceDocument'])
    ->orderBy('country_name')
    ->orderBy('organization_name')
    ->chunk(200, function ($candidates) use ($out) {
        foreach ($candidates as $candidate) {
            $country = $candidate->country;

            fputcsv($out, [
                '',
                $candidate->id,
                $candidate->status,
                $country?->name ?? $candidate->country_name,
                $country?->iso_code ?? $candidate->country_iso,
                $country?->region,
                $country?->default_language_code,
                $candidate->organization_name,
                $candidate->market_organization_id,
                $candidate->source_document_id,
                $candidate->sourceDocument?->title,
            ]);
        }
    });

fclose($out);

echo url('sls-exports/' . $fileName) . PHP_EOL;
echo SocialSecurityAdminCandidate::count() . ' entity rows' . PHP_EOL;
