<?php

namespace App\Support;

class EurLexTitleCleaner
{
    public static function clean(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? $title);

        if ($title === '') {
            return '';
        }

        foreach ([
            '/\s+https?:\/\/\S+.*$/iu',
            '/\s+\b[0-9]{4}-[0-9]{2}-[0-9]{2}\s+L_[A-Z0-9._-]+.*$/iu',
            '/\s+\b3[0-9]{4}[A-Z]{1,3}[0-9A-Z]{3,}(?:\([0-9A-Z]+\))?\b.*$/iu',
            '/\s+\bC\/[0-9]{4}\/[0-9]{4,}\b.*$/iu',
        ] as $pattern) {
            $cleaned = preg_replace($pattern, '', $title);

            if (is_string($cleaned)) {
                $title = trim($cleaned);
            }
        }

        if (preg_match('/\b(?:Commission|Council|European Parliament|Regulation|Directive|Decision|Corrigendum|Proposal|Communication|Report)\b.*$/u', $title, $matches) === 1) {
            $candidate = trim($matches[0]);

            if ($candidate !== '' && strlen($candidate) >= 30) {
                return $candidate;
            }
        }

        return $title;
    }
}
