<?php

namespace App\Services;

use App\Models\KnowledgeChunk;
use App\Models\Product;
use App\Models\SourceDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class KnowledgeIngestionService
{
    public function __construct(private KnowledgeChunkClassifier $classifier)
    {
    }

    public function ingest(UploadedFile $file, array $data): SourceDocument
    {
        $fullPath = $file->getRealPath() ?: $file->path();
        $text = $this->extractText($file, $fullPath);
        $text = $this->prepareTextForSourceType($text, $data['source_type'] ?? '');
        $path = $file->store('knowledge-sources');

        try {
            return DB::transaction(function () use ($data, $file, $path, $text) {
                $source = SourceDocument::create([
                    'title' => $data['title'],
                    'source_type' => $data['source_type'],
                    'intake_category' => $data['intake_category'] ?? null,
                    'intake_action' => $data['intake_action'] ?? null,
                    'contact_relationship_type' => $data['contact_relationship_type'] ?? null,
                    'related_country_id' => $data['related_country_id'] ?? null,
                    'related_organization_name' => $data['related_organization_name'] ?? null,
                    'intake_notes' => $data['intake_notes'] ?? null,
                    'language_code' => $data['language_code'] ?? 'en',
                    'original_filename' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'source_url' => $data['source_url'] ?? null,
                    'source_date' => $data['source_date'] ?? null,
                    'retrieved_at' => now(),
                    'approval_status' => 'unreviewed',
                    'approved_for_proposals' => false,
                ]);

                $productIds = collect($data['products'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values();

                $source->products()->sync($productIds);

                $primaryProductId = $productIds->first();
                $chunks = $this->chunkText($text);

                foreach ($chunks as $index => $chunkText) {
                    $chunkTitle = $source->title . ' - chunk ' . ($index + 1);
                    $classification = $this->classifier->classify($chunkTitle, $chunkText, $source->source_type);

                    KnowledgeChunk::create([
                        'source_document_id' => $source->id,
                        'product_id' => $primaryProductId,
                        'chunk_title' => $chunkTitle,
                        'chunk_text' => $chunkText,
                        'citation_label' => $source->title . ', chunk ' . ($index + 1),
                        ...$classification,
                        'approval_status' => 'unreviewed',
                    ]);
                }

                if (count($chunks) === 0) {
                    $classification = $this->classifier->classify($source->title . ' - extraction pending', 'Text extraction did not produce readable text. Review the original uploaded file.', $source->source_type);

                    KnowledgeChunk::create([
                        'source_document_id' => $source->id,
                        'product_id' => $primaryProductId,
                        'chunk_title' => $source->title . ' - extraction pending',
                        'chunk_text' => 'Text extraction did not produce readable text. Review the original uploaded file.',
                        'citation_label' => $source->title,
                        ...$classification,
                        'approval_status' => 'unreviewed',
                    ]);
                }

                return $source->load('products', 'chunks');
            });
        } catch (Throwable $exception) {
            Storage::delete($path);

            throw $exception;
        }
    }

    public function rebuildChunks(SourceDocument $source): SourceDocument
    {
        $fullPath = Storage::path($source->storage_path);
        $extension = strtolower(pathinfo((string) $source->original_filename, PATHINFO_EXTENSION));
        $text = $this->extractTextFromPath($extension, $fullPath);
        $text = $this->prepareTextForSourceType($text, $source->source_type);
        $chunks = $this->chunkText($text);
        $primaryProductId = $source->products()->value('products.id');

        return DB::transaction(function () use ($source, $chunks, $primaryProductId) {
            $source->chunks()->delete();

            foreach ($chunks as $index => $chunkText) {
                $chunkTitle = $source->title . ' - chunk ' . ($index + 1);
                $classification = $this->classifier->classify($chunkTitle, $chunkText, $source->source_type);

                KnowledgeChunk::create([
                    'source_document_id' => $source->id,
                    'product_id' => $primaryProductId,
                    'chunk_title' => $chunkTitle,
                    'chunk_text' => $chunkText,
                    'citation_label' => $source->title . ', chunk ' . ($index + 1),
                    ...$classification,
                    'approval_status' => 'unreviewed',
                ]);
            }

            if (count($chunks) === 0) {
                $classification = $this->classifier->classify($source->title . ' - extraction pending', 'Text extraction did not produce readable text. Review the original uploaded file.', $source->source_type);

                KnowledgeChunk::create([
                    'source_document_id' => $source->id,
                    'product_id' => $primaryProductId,
                    'chunk_title' => $source->title . ' - extraction pending',
                    'chunk_text' => 'Text extraction did not produce readable text. Review the original uploaded file.',
                    'citation_label' => $source->title,
                    ...$classification,
                    'approval_status' => 'unreviewed',
                ]);
            }

            return $source->load('products', 'chunks');
        });
    }

    private function extractText(UploadedFile $file, string $fullPath): string
    {
        return $this->extractTextFromPath(strtolower($file->getClientOriginalExtension()), $fullPath);
    }

    private function extractTextFromPath(string $extension, string $fullPath): string
    {
        if ($extension === 'pdf') {
            return trim((new PdfParser())->parseFile($fullPath)->getText());
        }

        if (in_array($extension, ['txt', 'csv', 'md', 'html', 'htm', 'eml'], true)) {
            return trim((string) file_get_contents($fullPath));
        }

        if ($extension === 'docx') {
            return $this->extractDocxText($fullPath);
        }

        if ($extension === 'xlsx') {
            return $this->extractXlsxText($fullPath);
        }

        return '';
    }

    private function extractDocxText(string $fullPath): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($fullPath) !== true) {
            return '';
        }

        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return $this->xmlText($xml);
    }

    private function extractXlsxText(string $fullPath): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($fullPath) !== true) {
            return '';
        }

        $parts = [];
        $sharedStrings = (string) $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStrings !== '') {
            $parts[] = $this->xmlText($sharedStrings);
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);

            if (is_string($name) && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                $parts[] = $this->xmlText((string) $zip->getFromName($name));
            }
        }

        $zip->close();

        return trim(implode("\n", array_filter($parts)));
    }

    private function xmlText(string $xml): string
    {
        $xml = preg_replace('/<[^>]+>/', ' ', $xml);
        $xml = html_entity_decode($xml ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');

        return trim(preg_replace('/\s+/', ' ', $xml ?? ''));
    }

    private function prepareTextForSourceType(string $text, string $sourceType): string
    {
        if (in_array($sourceType, ['webinar_transcript', 'product_demo_transcript'], true)) {
            $text = $this->cleanTranscriptText($text);
        }

        return $this->cleanDocumentBoilerplate($text);
    }

    private function cleanTranscriptText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\s*\d{1,2}:\d{2}:\d{2}(?:\.\d{1,3})?\s*-->\s*\d{1,2}:\d{2}:\d{2}(?:\.\d{1,3})?\s*(?:Speaker\s*\d+\s*)?/mi', '', $text);
        $text = preg_replace('/^\s*Speaker\s*\d+\s*:?[\t ]*/mi', '', $text);
        $text = preg_replace('/^\s*\d+\s*:\s*/m', '', $text);
        $text = preg_replace('/\b\d{1,2}:\d{2}:\d{2}(?:\.\d{1,3})?\s*-->\s*\d{1,2}:\d{2}:\d{2}(?:\.\d{1,3})?\s*(?:Speaker\s*\d+\s*)?/i', ' ', $text);
        $text = preg_replace('/\bSpeaker\s*\d+\s*:?/i', ' ', $text);

        return trim($text ?? '');
    }

    private function cleanDocumentBoilerplate(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $productPageLabels = [
            'Social Security Administration System\\s*\\(SSAS\\)',
            'Human Resources Management System\\s*\\(HRMS\\)',
            'Enterprise Risk Management System\\s*\\(ERMS\\)',
            'Electronic Benefits Payment Card\\s*\\(EBPC\\)',
        ];

        foreach ($productPageLabels as $label) {
            $text = preg_replace('/\\b' . $label . '\\s*(?:User\\s+Manual)?\\s*\\d+\\s*(?=Interact\\s+Inc\\.)/i', ' ', $text);
            $text = preg_replace('/\\b' . $label . '\\s*\\d+\\s*(?=Interact\\s+Inc\\.)/i', ' ', $text);
            $text = preg_replace('/^\\s*' . $label . '\\s*\\d+\\s*$/mi', ' ', $text);
        }

        $text = preg_replace('/\\b\\d*\\s*Interact\\s+Inc\\.,?\\s+Copyrights?\\s+\\d{4},?\\s+All\\s+Rights?\\s+Reserved\\s*\\d*\\b/i', ' ', $text);
        $text = preg_replace('/^\\s*(?:Page\\s*)?\\d+\\s*$/mi', ' ', $text);
        $text = preg_replace('/\bself\s+employed\b/i', 'self-employed', $text);
        $text = preg_replace('/\bself\s+employment\b/i', 'self-employment', $text);

        return trim($text ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        $clean = $this->normalizeText($text);

        if ($clean === '') {
            return [];
        }

        return collect(Str::of($clean)->split('/(?<=\.|\?|!)\s+/'))
            ->reduce(function (array $chunks, string $sentence) {
                $sentence = trim($sentence);

                if ($sentence === '') {
                    return $chunks;
                }

                $lastIndex = count($chunks) - 1;

                if ($lastIndex < 0 || strlen($chunks[$lastIndex] . ' ' . $sentence) > 1800) {
                    $chunks[] = $sentence;
                } else {
                    $chunks[$lastIndex] .= ' ' . $sentence;
                }

                return $chunks;
            }, []);
    }

    private function normalizeText(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
        $text = str_replace(["\u{2018}", "\u{2019}", "\u{201C}", "\u{201D}"], "'", $text);
        $text = str_replace(["\u{2013}", "\u{2014}"], '-', $text);
        $text = str_replace("\u{FFFD}", "'", $text);
        $text = preg_replace('/\bself\s+employed\b/i', 'self-employed', $text);
        $text = preg_replace('/\bself\s+employment\b/i', 'self-employment', $text);
        $text = preg_replace('/[^\P{C}\r\n\t]+/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text ?? '';
    }
}
