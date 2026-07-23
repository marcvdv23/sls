<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SocialSecurityAdminNameCleaner
{
    /**
     * @param iterable<int,string|null> $names
     * @param array<int,string> $countryAliases
     * @return Collection<int,string>
     */
    public static function canonicalizeList(iterable $names, ?string $countryName = null, ?string $iso = null, array $countryAliases = []): Collection
    {
        return collect($names)
            ->map(fn ($name) => self::cleanCountrySuffix((string) $name, $countryName, $iso, $countryAliases))
            ->filter()
            ->unique(fn (string $name) => self::normalize($name))
            ->groupBy(fn (string $name) => self::canonicalKey($name, $countryName, $iso, $countryAliases))
            ->map(fn (Collection $group) => self::preferredName($group, $countryName, $iso, $countryAliases))
            ->filter()
            ->values();
    }

    /**
     * @param array<int,string> $countryAliases
     */
    public static function cleanCountrySuffix(string $name, ?string $countryName = null, ?string $iso = null, array $countryAliases = []): string
    {
        $name = self::repairMojibake($name);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        $name = trim($name, " \t\n\r\0\x0B-;,");

        if ($name === '') {
            return '';
        }

        $variants = self::countryVariants($countryName, $iso, $countryAliases);

        foreach ($variants as $variant) {
            $quoted = preg_quote($variant, '/');
            $name = preg_replace('/\s*\((?:' . $quoted . ')\)\s*$/iu', '', $name) ?? $name;
            $name = preg_replace('/\s*[-,]\s*(?:' . $quoted . ')\s*$/iu', '', $name) ?? $name;
            $name = preg_replace('/\s+(?:of|du|de|del|do|da|dos|das|de la|de l\')\s+(?:' . $quoted . ')\s*$/iu', '', $name) ?? $name;
            $name = preg_replace('/\s+(?:' . $quoted . ')\s*$/iu', '', $name) ?? $name;
            $name = preg_replace('/^(?:' . $quoted . ')\s+/iu', '', $name) ?? $name;
        }

        return trim(preg_replace('/\s+/', ' ', $name) ?? '', " \t\n\r\0\x0B-;,");
    }

    public static function repairMojibake(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        if (preg_match('/(?:Ã|Â)/u', $value)) {
            for ($i = 0; $i < 3; $i++) {
                $converted = @iconv('UTF-8', 'Windows-1252//IGNORE', $value);

                if (! is_string($converted) || $converted === '' || $converted === $value || ! mb_check_encoding($converted, 'UTF-8')) {
                    break;
                }

                $value = $converted;

                if (! preg_match('/(?:Ã|Â)/u', $value)) {
                    break;
                }
            }
        }

        $value = strtr($value, [
            'ÔÇÖ' => "'",
            'ÔÇ£' => '"',
            'ÔÇØ' => '"',
            'ÔÇô' => '-',
            'ÔÇö' => '-',
            'ÔÇª' => '...',
            '├Ç' => 'À',
            '├ü' => 'Á',
            '├é' => 'Â',
            '├â' => 'Ã',
            '├ä' => 'Ä',
            '├à' => 'Å',
            '├ç' => 'Ç',
            '├ê' => 'Ê',
            '├ë' => 'Ë',
            '├ì' => 'Ì',
            '├î' => 'Î',
            '├ï' => 'Ï',
            '├æ' => 'Ñ',
            '├ö' => 'Ö',
            '├û' => 'Û',
            '├Ü' => 'Ü',
            '├á' => 'á',
            '├à' => 'à',
            '├â' => 'â',
            '├ä' => 'ä',
            '├ã' => 'ã',
            '├ç' => 'ç',
            '├®' => 'é',
            '├©' => 'é',
            '├¿' => 'è',
            '├ª' => 'ê',
            '├«' => 'î',
            '├¯' => 'ï',
            '├´' => 'ó',
            '├ô' => 'ô',
            '├Â' => 'Â',
            '├º' => 'ç',
            '├╣' => 'ù',
            '├╗' => 'û',
            '├╝' => 'ü',
            '┬á' => ' ',
        ]);

        if (preg_match('/(?:├|┬|┼|ÔÇ|ΓÇ|╬|┤|┐|└|┘|┌|─)/u', $value)) {
            $converted = @iconv('UTF-8', 'CP437//IGNORE', $value);

            if (is_string($converted) && $converted !== '' && $converted !== $value && mb_check_encoding($converted, 'UTF-8')) {
                $value = $converted;
            }
        }

        if (! preg_match('/(?:Ãƒ|Ã‚|Ã¢|Ã†|Æ’|â‚¬|Â¢|â€ž)/u', $value)) {
            return trim($value);
        }

        for ($i = 0; $i < 4; $i++) {
            $converted = @iconv('UTF-8', 'Windows-1252//IGNORE', $value);

            if (! is_string($converted) || $converted === '' || $converted === $value || ! mb_check_encoding($converted, 'UTF-8')) {
                break;
            }

            $value = $converted;

            if (! preg_match('/(?:Ãƒ|Ã‚|Ã¢|Ã†|Æ’|â‚¬|Â¢|â€ž)/u', $value)) {
                break;
            }
        }

        return trim($value);
    }
    /**
     * @param array<int,string> $countryAliases
     */
    private static function preferredName(Collection $group, ?string $countryName, ?string $iso, array $countryAliases): string
    {
        $hasRomanceOriginal = $group->contains(fn (string $name) => self::isRomanceOriginal($name));

        return $group
            ->sortBy(function (string $name) use ($hasRomanceOriginal, $countryName, $iso, $countryAliases) {
                $languageRank = $hasRomanceOriginal
                    ? (self::isRomanceOriginal($name) ? 0 : 2)
                    : (self::looksEnglish($name) ? 0 : 1);
                $countryRank = self::containsCountryMarker($name, $countryName, $iso, $countryAliases) ? 1 : 0;
                $wordCount = str_word_count(self::normalize($name));

                return sprintf('%02d-%02d-%03d-%04d-%s', $languageRank, $countryRank, $wordCount, strlen($name), self::normalize($name));
            })
            ->first();
    }

    /**
     * @param array<int,string> $countryAliases
     */
    private static function canonicalKey(string $name, ?string $countryName, ?string $iso, array $countryAliases): string
    {
        $normalized = self::normalize(self::cleanCountrySuffix($name, $countryName, $iso, $countryAliases));

        $equivalents = [
            'national social security fund' => [
                'national social security fund',
                'caisse nationale de securite sociale',
                'caja nacional de seguridad social',
                'caixa nacional de seguranca social',
            ],
            'national social insurance fund' => [
                'national social insurance fund',
                'caisse nationale dassurance sociale',
                'caisse nationale d assurance sociale',
                'caja nacional de seguro social',
                'caixa nacional de seguro social',
            ],
            'national social welfare fund' => [
                'national social welfare fund',
                'caisse nationale de prevoyance sociale',
                'institut national de prevoyance sociale',
            ],
            'social security institute' => [
                'social security institute',
                'instituto de seguridad social',
                'instituto nacional de seguridad social',
                'instituto nacional de seguranca social',
            ],
            'social insurance institute' => [
                'social insurance institute',
                'instituto de seguros sociales',
                'instituto de seguro social',
                'instituto nacional de seguro social',
            ],
        ];

        foreach ($equivalents as $key => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalized, $phrase)) {
                    return $key;
                }
            }
        }

        return $normalized;
    }

    private static function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private static function isRomanceOriginal(string $name): bool
    {
        $normalized = self::normalize($name);

        return preg_match('/\b(caisse|securite sociale|prevoyance|assurances sociales|retraite|caja|seguridad social|prevision|pensiones|tesoreria|caixa|seguranca social|previdencia|previdencia social|instituto nacional de seguranca|instituto nacional de seguridad)\b/i', $normalized) === 1;
    }

    private static function looksEnglish(string $name): bool
    {
        $normalized = self::normalize($name);

        return preg_match('/\b(ministry|department|national|social|security|insurance|fund|pension|health|labour|labor|employment|authority|office|agency|board|service|administration|commission|institute|provident|welfare)\b/i', $normalized) === 1;
    }

    /**
     * @param array<int,string> $countryAliases
     */
    private static function containsCountryMarker(string $name, ?string $countryName, ?string $iso, array $countryAliases): bool
    {
        $normalized = self::normalize($name);

        foreach (self::countryVariants($countryName, $iso, $countryAliases) as $variant) {
            $variant = self::normalize($variant);

            if ($variant !== '' && preg_match('/(^|\s)' . preg_quote($variant, '/') . '(\s|$)/', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,string> $countryAliases
     * @return array<int,string>
     */
    private static function countryVariants(?string $countryName, ?string $iso, array $countryAliases): array
    {
        return collect([$countryName, $iso])
            ->merge($countryAliases)
            ->filter()
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->unique(fn (string $value) => self::normalize($value))
            ->values()
            ->all();
    }
}
