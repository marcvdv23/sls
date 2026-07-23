<?php

namespace App\Services;

use Illuminate\Support\Collection;

class AiAnswerService
{
    public function __construct(
        private GeminiAnswerService $gemini,
        private OllamaAnswerService $ollama,
    ) {
    }

    /**
     * @param Collection<int, array{chunk: \App\Models\KnowledgeChunk, excerpt: string, score: int}> $matches
     */
    public function draft(string $question, Collection $matches, string $fallbackAnswer, string $answerLanguage = 'English'): string
    {
        return match ($this->provider()) {
            'ollama', 'mistral', 'local' => $this->ollama->draft($question, $matches, $fallbackAnswer, $answerLanguage),
            default => $this->gemini->draft($question, $matches, $fallbackAnswer, $answerLanguage),
        };
    }

    private function provider(): string
    {
        return strtolower(trim((string) config('services.ai.provider', 'gemini')));
    }
}
