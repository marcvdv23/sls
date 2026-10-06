<?php

namespace App\Support;

use Illuminate\Support\Str;

class EurLexDocumentClassifier
{
    public static function classify(?string $celex, string $title = '', string $sourceName = '', string $sourceUrl = ''): array
    {
        $celex = strtoupper(trim((string) $celex));
        $text = Str::lower(trim(implode(' ', [$title, $sourceName, $sourceUrl, $celex])));

        return [
            'legal_document_code' => $celex !== '' ? $celex : null,
            'legal_instrument_type' => self::instrumentType($celex, $text),
            'legislation_stage' => self::stage($celex, $text),
        ];
    }

    private static function instrumentType(string $celex, string $text): string
    {
        if (Str::contains($text, 'corrigendum')) {
            return 'corrigendum';
        }

        if (preg_match('/^3[0-9]{4}R/i', $celex) === 1 || Str::contains($text, ['regulation (eu)', 'implementing regulation', 'delegated regulation'])) {
            return 'regulation';
        }

        if (preg_match('/^3[0-9]{4}L/i', $celex) === 1 || Str::contains($text, ['directive (eu)', 'council directive', 'commission directive'])) {
            return 'directive';
        }

        if (preg_match('/^3[0-9]{4}D/i', $celex) === 1 || Str::contains($text, ['decision (eu)', 'implementing decision', 'council decision', 'commission decision'])) {
            return 'decision';
        }

        if (Str::contains($text, ['proposal for a regulation', 'proposal for a directive', 'proposal for a decision', 'commission proposal']) || preg_match('/^5[0-9]{4}PC/i', $celex) === 1) {
            return 'proposal';
        }

        if (Str::contains($text, 'communication from the commission')) {
            return 'communication';
        }

        if (Str::contains($text, 'report from the commission')) {
            return 'report';
        }

        if (Str::contains($text, 'recommendation')) {
            return 'recommendation';
        }

        if (Str::contains($text, 'opinion')) {
            return 'opinion';
        }

        return 'other';
    }

    private static function stage(string $celex, string $text): string
    {
        if (Str::contains($text, 'corrigendum')) {
            return 'corrigendum';
        }

        if (Str::contains($text, ['proposal for a', 'commission proposal', 'preparatory act']) || preg_match('/^5[0-9]{4}PC/i', $celex) === 1) {
            return 'proposal';
        }

        if (preg_match('/^3[0-9]{4}[RLD]/i', $celex) === 1) {
            return 'adopted';
        }

        if (Str::contains($text, ['official journal l', 'official journal of the european union l series'])) {
            return 'adopted';
        }

        return 'other';
    }
}
