<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OllamaAnswerService
{
    /**
     * @param Collection<int, array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    public function draft(string $question, Collection $matches, string $fallbackAnswer, string $answerLanguage = 'English'): string
    {
        if (! $this->isConfigured()) {
            return $fallbackAnswer;
        }

        try {
            $response = Http::timeout((int) config('services.ollama.timeout', 120))
                ->post($this->endpoint(), [
                    'model' => (string) config('services.ollama.model'),
                    'prompt' => $this->prompt($question, $matches, $answerLanguage),
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.2,
                        'top_p' => 0.8,
                        'num_predict' => 120,
                    ],
                ]);

            if (! $response->successful()) {
                return $fallbackAnswer;
            }

            $text = data_get($response->json(), 'response');

            if (! is_string($text) || trim($text) === '') {
                return $fallbackAnswer;
            }

            return $this->cleanGeneratedAnswer($text);
        } catch (Throwable $exception) {
            report($exception);

            return $fallbackAnswer;
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('services.ollama.model'));
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('services.ollama.endpoint'), '/');

        return $base . '/api/generate';
    }

    private function apiFailureMessage(Response $response): string
    {
        $message = data_get($response->json(), 'error');

        if (is_string($message) && trim($message) !== '') {
            $message = preg_replace('/\s+/', ' ', trim($message));
            $message = Str::limit($message, 500);
        } else {
            $message = 'No detailed message was returned by Ollama.';
        }

        return 'Local Mistral could not draft the answer. Ollama response ' . $response->status() . ': ' . $message;
    }

    /**
     * @param Collection<int, array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function prompt(string $question, Collection $matches, string $answerLanguage): string
    {
        $context = $matches
            ->take(1)
            ->map(function (array $match, int $index) {
                $chunk = $match['chunk'];
                $citation = $chunk->citation_label ?: $chunk->chunk_title;
                $source = $chunk->sourceDocument?->title ?: 'Uploaded source';
                $product = $chunk->product?->name ?: 'Selected product';
                $text = Str::limit($this->cleanText($chunk->chunk_text), 180);

                return 'SOURCE ' . ($index + 1) . "\n"
                    . 'Citation: ' . $citation . "\n"
                    . 'Product: ' . $product . "\n"
                    . 'Document: ' . $source . "\n"
                    . 'Content type: ' . ($chunk->content_type ?: 'unclassified') . "\n"
                    . 'Business area: ' . ($chunk->business_area ?: 'general') . "\n"
                    . 'Text: ' . $text;
            })
            ->implode("\n\n---\n\n");

        return <<<PROMPT
Answer in {$answerLanguage}, matching the user's question. Use only this source. Keep under 100 words. Cite the source label. Use clean prose. Prefer configuration and business logic over procedure steps unless the user asks how to perform a task. For contribution calculation questions, state first that configured contribution rates, rules, and formulas are applied to applicable insurable earnings or the contribution base. For benefit claim calculation questions, state first that configured benefit policies, eligibility rules, entitlement rules, calculation methods, formulas, and rate tables are applied to claimant and claim data. Do not copy raw manual navigation paths, ">>>", "?", figure labels, click-step text, or Markdown symbols such as * and **. Keep product names and citation labels unchanged.

Question: {$question}

{$context}
PROMPT;
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{00AD}", "\u{00FF}", '??'], ' ', $text);
        $text = str_replace(['?ex', '?ow', 'Uni?ed', 'uni?ed', 'Bene?ts', 'bene?ts', 'Certi?cates'], ['flex', 'flow', 'Unified', 'unified', 'Benefits', 'benefits', 'Certificates'], $text);
        $text = str_replace(['stafing', 'Stafing', 'stafffing', 'Stafffing', 'diferent', 'Diferent', 'efective', 'Efective'], ['staffing', 'Staffing', 'staffing', 'Staffing', 'different', 'Different', 'effective', 'Effective'], $text);
        $text = str_replace(['atendance', 'Atendance', 'eficient', 'Eficient', 'eficiency', 'Eficiency', 'ofers', 'Ofers', 'ofice', 'Ofice', 'leters', 'Leters', 'staf'], ['attendance', 'Attendance', 'efficient', 'Efficient', 'efficiency', 'Efficiency', 'offers', 'Offers', 'office', 'Office', 'letters', 'Letters', 'staff'], $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text ?? '');
    }

    private function cleanGeneratedAnswer(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/\*\*(.*?)\*\*/s', '$1', $text);
        $text = preg_replace('/\*(.*?)\*/s', '$1', $text);
        $text = preg_replace('/^\s*#{1,6}\s*/m', '', $text);
        $text = preg_replace('/^\s*[-*]\s+/m', '', $text);
        $text = preg_replace('/^\s*\d+\.\s+/m', '', $text);
        $text = preg_replace('/\[(?:SOURCE\s*)?\d+\]/i', '', $text);
        $text = preg_replace('/\[\s*\d+(?:\s*,\s*\d+)*\s*\]/', '', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($this->normalizeProductAcronyms($text ?? ''));
    }

    private function normalizeProductAcronyms(string $text): string
    {
        $text = preg_replace('/\bSsas\b/i', 'SSAS', $text) ?? $text;
        $text = preg_replace('/\bHrms\b/i', 'HRMS', $text) ?? $text;
        $text = preg_replace('/\bErms\b/i', 'ERMS', $text) ?? $text;
        $text = preg_replace('/\bEbpc\b/i', 'EBPC', $text) ?? $text;

        return $text;
    }
}
