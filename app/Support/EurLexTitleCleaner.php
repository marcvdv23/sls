<?php

namespace App\Support;

class EurLexTitleCleaner
{
    public static function clean(string $title): string
    {
        $title = self::squish($title);

        if ($title === '') {
            return '';
        }

        $descriptiveTitle = self::descriptiveTitle($title);
        if ($descriptiveTitle !== '') {
            return $descriptiveTitle;
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

        $descriptiveTitle = self::descriptiveTitle($title);
        if ($descriptiveTitle !== '') {
            return $descriptiveTitle;
        }

        if ($title === '' || self::isMetadataOnly($title)) {
            return '';
        }

        return $title;
    }

    public static function fallbackTitle(?string $code, ?string $type = null): string
    {
        $label = self::label($type ?: 'legislation');
        $code = strtoupper(trim((string) $code));

        return trim('EUR-Lex ' . $label . ($code !== '' ? ' ' . $code : ''));
    }

    public static function isFallbackTitle(string $title): bool
    {
        return preg_match('/^EUR-Lex\s+(?:Legislation|Regulation|Directive|Decision|Proposal|Corrigendum|Communication|Report|Other)(?:\s+[0-9A-Z\/()]+)?$/i', trim($title)) === 1;
    }

    public static function isMetadataOnly(string $title): bool
    {
        $title = self::squish($title);

        if ($title === '') {
            return true;
        }

        $lower = strtolower($title);

        return str_starts_with($lower, 'eng_cellar:')
            || str_contains($lower, ' all_all ')
            || str_contains($lower, ' eu_law_all ')
            || str_contains($lower, ' published_in_oj');
    }

    private static function label(?string $type): string
    {
        $label = trim(str_replace('_', ' ', (string) $type));

        return $label !== '' ? ucwords($label) : 'Legislation';
    }

    private static function descriptiveTitle(string $title): string
    {
        if (preg_match('/\b(?:Commission|Council|European Parliament|Regulation|Directive|Decision|Corrigendum|Proposal|Communication|Report)\b.*$/u', $title, $matches) !== 1) {
            return '';
        }

        $candidate = trim($matches[0]);

        foreach ([
            '/\s+https?:\/\/\S+.*$/iu',
            '/\s+\b[0-9]{4}-[0-9]{2}-[0-9]{2}\s+L_[A-Z0-9._-]+.*$/iu',
            '/\s+\b3[0-9]{4}[A-Z]{1,3}[0-9A-Z]{3,}(?:\([0-9A-Z]+\))?\b.*$/iu',
        ] as $pattern) {
            $cleaned = preg_replace($pattern, '', $candidate);

            if (is_string($cleaned)) {
                $candidate = trim($cleaned);
            }
        }

        return $candidate !== '' && strlen($candidate) >= 30 ? $candidate : '';
    }

    private static function squish(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
