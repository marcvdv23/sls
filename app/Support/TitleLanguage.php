<?php

namespace App\Support;

use Illuminate\Support\Str;

class TitleLanguage
{
    public static function looksNonEnglish(string $title): bool
    {
        $normalized = ' ' . Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim($title)) ?? '')) . ' ';

        if (trim($normalized) === '') {
            return false;
        }

        if (self::looksEncodedNoise($title) || self::looksMostlyEnglish($title)) {
            return false;
        }

        if (preg_match('/[^\p{Latin}\p{Common}\p{Inherited}]/u', $title) === 1) {
            return true;
        }

        if (preg_match('/[áéíóúüñ¿¡çãõâêôàèìòùäëïö‚¢Š]/iu', $title) === 1) {
            return true;
        }

        $foreignSignals = [
            ' adquisicion ', ' advierte ', ' ahora ', ' ainsi ', ' appel ', ' asistencia ', ' avec ',
            ' brasil ', ' caribe ', ' compra ', ' contratacion ', ' convocatoria ', ' corrupcion ',
            ' denuncia ', ' denunciar ', ' des ', ' desde ', ' de ', ' del ', ' dos ', ' du ',
            ' elevan ', ' en ', ' entrepreneuriat ', ' espagnol ', ' et ', ' fortalece ', ' gestion ',
            ' impots ', ' inclusion numerique ', ' la ', ' lanza ', ' las ', ' le ', ' les ',
            ' licitacion ', ' los ', ' mexicana ', ' mexico ', ' para ', ' paises ', ' por ',
            ' portugues ', ' projet ', ' proyecto ', ' recaudacion ', ' reformas ', ' rezago ',
            ' securite sociale ', ' tributaria ', ' tributarias ', ' une ',
        ];

        if (Str::contains($normalized, $foreignSignals)) {
            return true;
        }

        $englishSignals = [
            ' and ', ' for ', ' in ', ' of ', ' on ', ' procurement ', ' supply ', ' tender ',
            ' the ', ' to ', ' with ',
        ];

        return ! Str::contains($normalized, $englishSignals)
            && Str::contains($normalized, [' reforma', ' tribut', ' recaud', ' cepal', ' pais', ' mexico']);
    }

    public static function isUsableEnglishTitle(string $englishTitle, string $originalTitle): bool
    {
        $englishTitle = trim($englishTitle);
        $originalTitle = trim($originalTitle);

        if ($englishTitle === '' || Str::lower($englishTitle) === 'translation pending') {
            return false;
        }

        if (self::looksEncodedNoise($englishTitle)) {
            return false;
        }

        if ($originalTitle !== '' && $englishTitle === $originalTitle && self::looksNonEnglish($originalTitle)) {
            return false;
        }

        return true;
    }

    public static function looksEncodedNoise(string $text): bool
    {
        $text = trim($text);

        if ($text === '') {
            return false;
        }

        if (preg_match('/^CBMi[A-Za-z0-9_-]{20,}$/', $text) === 1) {
            return true;
        }

        return strlen($text) >= 48
            && ! str_contains($text, ' ')
            && preg_match('/^[A-Za-z0-9+\/=_-]+$/', $text) === 1;
    }

    private static function looksMostlyEnglish(string $title): bool
    {
        $ascii = Str::lower(Str::ascii($title));
        $normalized = ' ' . (preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '') . ' ';
        $tokens = array_values(array_filter(explode(' ', trim($normalized))));

        if ($tokens === []) {
            return false;
        }

        $commonEnglishWords = [
            'a', 'against', 'all', 'alone', 'and', 'bank', 'benefit', 'capacity', 'case',
            'call', 'concludes', 'creates', 'employment', 'enhanced', 'faces', 'for', 'fund', 'geopolitical', 'held', 'in',
            'installation', 'institutional', 'jobs', 'labour', 'management', 'ministry',
            'national', 'nearly', 'negotiations', 'not', 'of', 'on', 'open', 'pension',
            'policy', 'poverty', 'procurement', 'reform', 'security', 'services', 'social', 'solve',
            'strengthen', 'supply', 'system', 'tender', 'the', 'to', 'training', 'under',
            'vigilance', 'vote', 'was', 'will', 'with', 'without',
        ];

        $hits = 0;

        foreach ($tokens as $token) {
            if (in_array($token, $commonEnglishWords, true)) {
                $hits++;
            }
        }

        if ($hits >= 2) {
            return true;
        }

        return $hits === 1 && count($tokens) <= 3;
    }
}
