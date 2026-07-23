<?php

use App\Models\Product;
use App\Services\KnowledgeIngestionService;
use Illuminate\Http\UploadedFile;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$productId = Product::where('code', 'interact-ssas')->value('id');

$file = new UploadedFile(
    storage_path('app/test-product-source.txt'),
    'test-product-source.txt',
    'text/plain',
    null,
    true
);

$source = app(KnowledgeIngestionService::class)->ingest($file, [
    'title' => 'Test SSAS Source',
    'source_type' => 'manual',
    'language_code' => 'en',
    'products' => [$productId],
]);

echo $source->id . ':' . $source->chunks()->count() . PHP_EOL;
