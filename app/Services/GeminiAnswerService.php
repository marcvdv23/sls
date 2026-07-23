<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GeminiAnswerService
{
    public function translateTitle(string $title, string $sourceUrl = '', string $country = '', string $sourceName = ''): string
    {
        $fallback = trim($title);

        if (! $this->isConfigured() || $fallback === '') {
            return $fallback;
        }

        try {
            $response = Http::timeout(25)
                ->withOptions($this->httpOptions())
                ->withHeaders([
                    'x-goog-api-key' => (string) config('services.gemini.api_key'),
                    'Content-Type' => 'application/json',
                ])
                ->post($this->endpoint(), [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $this->titleTranslationPrompt($title, $sourceUrl, $country, $sourceName),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.0,
                        'topP' => 0.3,
                        'maxOutputTokens' => 120,
                    ],
            ]);

            if (! $response->successful()) {
                $this->logWarning('Gemini title translation failed.', [
                    'status' => $response->status(),
                    'message' => data_get($response->json(), 'error.message'),
                    'title' => Str::limit($fallback, 160),
                ]);

                return $fallback;
            }

            $translated = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($translated) || trim($translated) === '') {
                $this->logWarning('Gemini title translation returned an empty response.', [
                    'title' => Str::limit($fallback, 160),
                ]);

                return $fallback;
            }

            return Str::limit($this->cleanTranslatedTitle($translated), 500, '');
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    /**
     * @param Collection<int, array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    public function draft(string $question, Collection $matches, string $fallbackAnswer, string $answerLanguage = 'English'): string
    {
        if (! $this->isConfigured()) {
            return $fallbackAnswer;
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
                            'text' => $this->prompt($question, $matches, $answerLanguage),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'topP' => 0.8,
                        'maxOutputTokens' => 1800,
                    ],
                ]);

            if (! $response->successful()) {
                return $fallbackAnswer;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                return $fallbackAnswer;
            }

            return $this->cleanGeneratedAnswer($text);
        } catch (Throwable $exception) {
            report($exception);

            return $fallbackAnswer;
        }
    }

    /**
     * @return array<string, string>
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

    private function apiFailureMessage(Response $response): string
    {
        $status = $response->status();
        $message = data_get($response->json(), 'error.message');

        if (is_string($message) && trim($message) !== '') {
            $message = preg_replace('/\s+/', ' ', trim($message));
            $message = Str::limit($message, 500);
        } else {
            $message = 'No detailed message was returned by Gemini.';
        }

        if ($status === 429) {
            return 'Gemini is connected, but Google returned a quota/rate-limit error (429): ' . $message;
        }

        if ($status === 401 || $status === 403) {
            return 'Gemini is connected, but Google rejected the API key or project permissions (' . $status . '): ' . $message;
        }

        if ($status === 404) {
            return 'Gemini is connected, but the configured model or endpoint was not found (404): ' . $message;
        }

        return 'Gemini could not draft the answer. API response ' . $status . ': ' . $message;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    private function endpoint(): string
    {
        $base = rtrim((string) config('services.gemini.endpoint'), '/');
        $model = trim((string) config('services.gemini.model'));

        return $base . '/models/' . rawurlencode($model) . ':generateContent';
    }

    private function titleTranslationPrompt(string $title, string $sourceUrl, string $country, string $sourceName): string
    {
        $decodedUrl = rawurldecode($sourceUrl);

        return <<<PROMPT
Translate the source title to clear English.

Rules:
- Return only the English title.
- Do not add explanations, labels, quotation marks, Markdown, or source notes.
- Do not invent a different story.
- If the title is already English, return it cleaned up.
- If the title text is garbled but the URL slug is readable, use the URL slug to infer the title.

Country: {$country}
Source: {$sourceName}
Original title: {$title}
Source URL: {$decodedUrl}
PROMPT;
    }

    private function cleanTranslatedTitle(string $title): string
    {
        $title = preg_replace('/\s+/', ' ', trim($title)) ?? '';
        $title = trim($title, " \t\n\r\0\x0B\"'`*#");

        return $this->normalizeProductAcronyms($title);
    }

    /**
     * @param Collection<int, array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function prompt(string $question, Collection $matches, string $answerLanguage): string
    {
        $context = $this->structuredContext($matches);

        return <<<PROMPT
You are the 1G-SLS product knowledge assistant.

Answer the user's question using only the approved source material below.

Rules:
- Be direct and business-like.
- Answer in clean prose and short, meaningful bullets.
- Use all source material that is relevant to the question.
- Treat the source material as a structured evidence bundle. Give highest importance to Core configuration and business logic, then Definitions and screen purpose, then Workflow outcomes, then Transaction processing, then Procedure steps.
- Do not invent product features, module names, statistics, countries, laws, dates, integrations, or claims.
- If the answer is incomplete because the sources do not contain enough detail, say that clearly.
- Cite sources inline using their citation labels in square brackets.
- Do not cite SOURCE numbers. Use only the Citation value shown for each source.
- If the user asks for a list, provide a complete list from the supplied sources.
- Preserve product names exactly, such as Interact SSAS and Interact HRMS.
- Clean obvious PDF extraction artifacts only when the intended word is clear, such as "eficient" to "efficient" and "atendance" to "attendance".
- Do not copy raw manual navigation paths, figure labels, click instructions, ">>>", "?", or symbol bullets into the answer.
- Do not use Markdown formatting characters such as *, **, ###, or numbered source references like [7].
- For functional questions, answer in this order: general setup/configuration capabilities; how those settings affect system behavior; transaction or workflow outcomes; important options or exceptions. Include procedure steps only when the user explicitly asks how to perform a task.
- For employer contribution filing through self-service, keep the focus on contribution filing. Treat self-service only as the employer-facing submission channel. Explain that the filing is driven by the employer record, contribution period, employee/member details, insurable earnings, and configured contribution rates/rules; then explain review, approval, payment, posting, adjustment, and reporting outcomes when supported by the sources.
- For contribution calculation questions, keep the answer concise. State first that contributions are calculated by applying configured contribution rates, rules, and formulas to the applicable insurable earnings or contribution base. Then mention only the most important relevant variations, such as employee/employer rates, employee groups, exemptions, ceilings, or self-employed/voluntary contributor assessments when supported by the sources.
- For benefit claim calculation questions, keep the answer concise. State first that benefit claims are calculated by applying configured benefit policies, eligibility rules, entitlement rules, calculation methods, formulas, and rate tables to the claimant and claim data. Then explain how those settings affect eligibility, entitlement, calculated amount, approvals, award/rejection letters, payments, suspensions, adjustments, and reporting when supported by the sources.
- For receivables management questions, keep the answer focused on invoices and amounts to be collected. Explain that it is used to create, track, edit, cancel, and follow up invoices for services, items, penalties, or other receivable amounts, and mention setup such as invoice numbering, invoice types, payment terms, service items, cancellation reasons, and late-payment interest only when supported by the sources. Do not answer with generic compliance, delinquency, audit, or benefit payment text unless the question specifically asks for those integrations.
- For "key benefits", "benefits of", "advantages of", or "value of" questions, treat benefits as business advantages, not the SSAS Benefits module. Synthesize the operational value in professional language. Do not quote "Form Usage" wording, raw procedure fragments, or field lists.
- For compliance management questions, focus on compliance audits/inspections, employer liabilities, delinquency or arrears follow-up, penalties, audit scheduling, officer responsibility, visits, findings, case tracking, and reporting. Do not discuss medical referee, benefit claim, or benefit payment functions unless the user explicitly asks about those integrations.
- Answer in {$answerLanguage}, matching the language of the user's question.
- Keep product names, module names, source titles, and citation labels unchanged.

User question:
{$question}

Approved source material:
{$context}
PROMPT;
    }

    /**
     * @param Collection<int, array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    private function structuredContext(Collection $matches): string
    {
        $sections = [
            'Core configuration and policy rules' => ['configuration', 'business_logic'],
            'Definitions and screen purpose' => ['glossary_definition', 'screen_purpose'],
            'Workflow outcomes' => ['workflow_outcome'],
            'Transaction processing' => ['transaction_processing', 'transcript_explanation'],
            'Procedure steps' => ['procedure_steps'],
            'Other relevant material' => [null, ''],
        ];

        $used = collect();
        $sourceNumber = 1;

        return collect($sections)
            ->map(function (array $types, string $section) use ($matches, $used, &$sourceNumber) {
                $sectionMatches = $matches
                    ->filter(function (array $match) use ($types, $used) {
                        $chunk = $match['chunk'];

                        return ! $used->contains($chunk->id)
                            && in_array($chunk->content_type, $types, true);
                    })
                    ->take($section === 'Procedure steps' ? 3 : 8)
                    ->values();

                $sectionMatches->each(fn (array $match) => $used->push($match['chunk']->id));

                if ($sectionMatches->isEmpty()) {
                    return null;
                }

                $body = $sectionMatches
                    ->map(function (array $match) use (&$sourceNumber) {
                        return $this->formatSource($match, $sourceNumber++);
                    })
                    ->implode("\n\n---\n\n");

                return "## {$section}\n\n{$body}";
            })
            ->filter()
            ->implode("\n\n==========\n\n");
    }

    /**
     * @param array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int} $match
     */
    private function formatSource(array $match, int $index): string
    {
        $chunk = $match['chunk'];
        $citation = $chunk->citation_label ?: $chunk->chunk_title;
        $source = $chunk->sourceDocument?->title ?: 'Uploaded source';
        $product = $chunk->product?->name ?: 'Selected product';
        $text = Str::limit($this->cleanText($chunk->chunk_text), 1500);

        return 'SOURCE ' . $index . "\n"
            . 'Citation: ' . $citation . "\n"
            . 'Product: ' . $product . "\n"
            . 'Document: ' . $source . "\n"
            . 'Content type: ' . ($chunk->content_type ?: 'unclassified') . "\n"
            . 'Business area: ' . ($chunk->business_area ?: 'general') . "\n"
            . 'Relevance score: ' . $match['score'] . "\n"
            . 'Text: ' . $text;
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
        $text = preg_replace('/\s+([.,;:!?])/', '$1', $text);
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

    /**
     * Logging must never break crawlers or backfill commands.
     *
     * @param array<string, mixed> $context
     */
    private function logWarning(string $message, array $context): void
    {
        try {
            Log::warning($message, $context);
        } catch (Throwable) {
            //
        }
    }
}
