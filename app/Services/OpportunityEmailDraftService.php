<?php

namespace App\Services;

use App\Models\CountryUpdate;
use App\Models\KnowledgeChunk;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OpportunityEmailDraftService
{
    /**
     * @param Collection<int, KnowledgeChunk> $chunks
     */
    public function draft(
        CountryUpdate $update,
        string $issueArea,
        string $issueSummary,
        string $productAlignment,
        Collection $chunks,
        ?Product $product,
        string $fallback,
    ): string {
        if (! $this->isConfigured()) {
            return $fallback;
        }

        try {
            $response = Http::timeout(45)
                ->withOptions($this->httpOptions())
                ->withHeaders([
                    'x-goog-api-key' => (string) config('services.gemini.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint(), [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $this->prompt($update, $issueArea, $issueSummary, $productAlignment, $chunks, $product),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.35,
                        'topP' => 0.85,
                        'maxOutputTokens' => 900,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini opportunity email draft failed.', [
                    'status' => $response->status(),
                    'message' => data_get($response->json(), 'error.message'),
                    'country_update_id' => $update->id,
                ]);

                return $fallback;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                return $fallback;
            }

            return $this->cleanDraft($text);
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    /**
     * @param Collection<int, KnowledgeChunk> $chunks
     */
    private function prompt(
        CountryUpdate $update,
        string $issueArea,
        string $issueSummary,
        string $productAlignment,
        Collection $chunks,
        ?Product $product,
    ): string {
        $country = $update->country?->name ?? 'Unknown country';
        $productName = $product?->name ?? 'the selected Interact product';
        $title = $update->title_english ?: $update->title_original ?: $update->title;
        $summary = $update->summary_english ?: $update->summary ?: 'No summary captured.';
        $source = trim(($update->source_name ?: 'Unknown source') . ' ' . ($update->source_url ?: ''));
        $date = $update->publication_date?->toDateString() ?: 'No publication date captured';
        $evidence = $this->knowledgeContext($chunks);

        return <<<PROMPT
Write a concise prospecting email draft for 1G-SLS.

The email is for internal editing before outreach. Make it useful, specific, and easy to personalize.

Rules:
- Return only the email draft, starting with "Subject:".
- Do not use Markdown, bullets, headings, or placeholders except "Hello,".
- Do not invent a recipient, meeting date, client relationship, procurement status, or product feature.
- Keep it under 180 words.
- Mention the article/story naturally in the first paragraph.
- Include one short positive/praise sentence about the article's usefulness or clarity.
- Connect the issue to the selected product using only the product fit and approved evidence below.
- Make the ask specific: offer to share a short workflow example or screenshots relevant to the issue.
- Avoid generic phrases like "may provide relevant operational support" unless you make them concrete.
- Use a professional, warm business tone.

Article context:
Country: {$country}
Publication/date: {$source} / {$date}
Title: {$title}
Story summary: {$summary}
Issue area: {$issueArea}
Issue summary prepared by SLS: {$issueSummary}

Selected product:
{$productName}

Product fit prepared by SLS:
{$productAlignment}

Approved product evidence:
{$evidence}
PROMPT;
    }

    /**
     * @param Collection<int, KnowledgeChunk> $chunks
     */
    private function knowledgeContext(Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            return 'No approved product evidence was matched. Stay conservative and only use the product fit text.';
        }

        return $chunks
            ->take(5)
            ->map(function (KnowledgeChunk $chunk, int $index): string {
                $title = $chunk->chunk_title ?: 'Untitled evidence';
                $citation = $chunk->citation_label ?: 'No citation';
                $text = Str::limit(preg_replace('/\s+/', ' ', (string) $chunk->chunk_text) ?? '', 700);

                return 'Evidence ' . ($index + 1) . ': ' . $title . ' | ' . $citation . ' | ' . $text;
            })
            ->implode("\n");
    }

    private function cleanDraft(string $draft): string
    {
        $draft = trim($draft);
        $draft = preg_replace('/^```(?:text)?\s*/i', '', $draft) ?? $draft;
        $draft = preg_replace('/\s*```$/', '', $draft) ?? $draft;
        $draft = preg_replace("/\n{3,}/", "\n\n", $draft) ?? $draft;

        return trim($draft);
    }

    /**
     * @return array<string, bool|string>
     */
    private function httpOptions(): array
    {
        if (! (bool) config('services.gemini.verify_ssl', true)) {
            return ['verify' => false];
        }

        $caBundle = (string) config('services.gemini.ca_bundle');

        if ($caBundle !== '' && is_file($caBundle)) {
            return ['verify' => $caBundle];
        }

        return [];
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('services.gemini.endpoint'), '/');
        $model = trim((string) config('services.gemini.model'));

        return $base . '/models/' . rawurlencode($model) . ':generateContent';
    }
}
