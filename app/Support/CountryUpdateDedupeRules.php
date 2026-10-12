<?php

namespace App\Support;

use App\Models\CountryUpdate;
use Illuminate\Support\Str;

class CountryUpdateDedupeRules
{
    public static function sourceFingerprint(string $url): ?string
    {
        $normalized = self::normalizeSourceUrl($url);

        return $normalized === '' ? null : hash('sha256', $normalized);
    }

    public static function normalizeSourceUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);

        if (! is_array($parts) || blank($parts['host'] ?? null)) {
            return Str::lower(rtrim($url, "/ \t\n\r\0\x0B"));
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? 'https'));
        $host = Str::lower((string) $parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?: $host;
        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        $path = rtrim($path, '/') ?: '/';

        $queryString = '';
        if (filled($parts['query'] ?? null)) {
            parse_str((string) $parts['query'], $query);
            $dropKeys = [
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'fbclid',
                'gclid',
                'mc_cid',
                'mc_eid',
                'oc',
                'cid',
            ];
            foreach ($dropKeys as $key) {
                unset($query[$key]);
            }
            ksort($query);
            $queryString = http_build_query($query);
        }

        return $scheme . '://' . $host . $path . ($queryString !== '' ? '?' . $queryString : '');
    }

    public static function semanticTitleKey(CountryUpdate|array $update): string
    {
        $title = self::field($update, 'title_english')
            ?: self::field($update, 'title')
            ?: self::field($update, 'title_original');

        $title = Str::lower(Str::ascii(trim($title)));
        $title = preg_replace('/\s+-\s+[a-z0-9][a-z0-9.-]+\.[a-z]{2,}\s*$/i', '', $title) ?? $title;
        $title = preg_replace('/[^a-z0-9]+/', ' ', $title) ?? $title;

        return trim($title);
    }

    public static function semanticSourceIdentity(CountryUpdate|array $update): string
    {
        $sourceName = Str::lower(self::field($update, 'source_name'));

        if (preg_match('/([a-z0-9][a-z0-9-]*(?:\.[a-z0-9][a-z0-9-]*)+\.[a-z]{2,})/i', $sourceName, $match) === 1) {
            return preg_replace('/^www\./', '', Str::lower($match[1])) ?: Str::lower($match[1]);
        }

        $host = Str::lower((string) parse_url(self::field($update, 'source_url'), PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?: $host;

        return $host ?: $sourceName;
    }

    /**
     * Return duplicate keys from strongest to broadest.
     *
     * Exact URL/fingerprint duplication is enforced separately by source_fingerprint.
     * These semantic keys catch repeated stories that arrive through different URLs
     * or aggregators. The broad key intentionally requires a publication date and
     * a substantial title so generic titles such as "Press Release" are not merged.
     *
     * @return array<int, string>
     */
    public static function semanticDuplicateKeys(CountryUpdate|array $update): array
    {
        $countryId = self::field($update, 'country_id');
        $publicationDate = self::publicationDate($update);
        $titleKey = self::semanticTitleKey($update);

        if ($countryId === '' || Str::length($titleKey) < 18) {
            return [];
        }

        $keys = [
            implode('|', [
                'story-source',
                $countryId,
                $publicationDate ?: 'no-date',
                self::semanticSourceIdentity($update),
                $titleKey,
            ]),
        ];

        if ($publicationDate !== '' && Str::length($titleKey) >= 24) {
            $keys[] = implode('|', [
                'story-title',
                $countryId,
                $publicationDate,
                $titleKey,
            ]);
        }

        $tenderFamilyKey = self::tenderFamilyKey($update);
        if ($tenderFamilyKey !== '') {
            $keys[] = implode('|', [
                'tendersontime-family',
                $countryId,
                $tenderFamilyKey,
            ]);
        }

        return array_values(array_unique($keys));
    }

    public static function tenderFamilyKey(CountryUpdate|array $update): string
    {
        if (! self::isTendersOnTime($update)) {
            return '';
        }

        $titleKey = self::semanticTitleKey($update);
        if (Str::length($titleKey) < 45) {
            return '';
        }

        $base = $titleKey;

        foreach ([
            '/\s+(?:in|for|at|under|across)\s+[a-z][a-z0-9 ]+$/',
            '/\s+(?:operation circles?|division|district|province|region|state|zone|lot|package)\s+[a-z0-9 ]+$/',
        ] as $pattern) {
            $candidate = preg_replace($pattern, '', $base) ?? $base;
            if (Str::length($candidate) >= 45) {
                $base = $candidate;
            }
        }

        $hasSystemSignal = preg_match('/\b(system|software|platform|application|solution|framework|portal|module|database|mis|erp|hris|hrms|hcm)\b/', $base) === 1
            || Str::contains($base, [
                'performance management',
                'asset performance',
                'benefits administration',
                'pension administration',
                'pension payroll',
                'human resources information',
                'enterprise resource planning',
                'budget management',
                'time and attendance',
            ]);

        if (! $hasSystemSignal || Str::length($base) < 45) {
            return '';
        }

        return trim($base);
    }

    public static function reviewDuplicateKey(CountryUpdate $update): string
    {
        $keys = self::semanticDuplicateKeys($update);

        if ($keys !== []) {
            return $keys[0];
        }

        return filled($update->source_url) ? 'url:' . Str::lower($update->source_url) : 'update:' . $update->id;
    }

    private static function publicationDate(CountryUpdate|array $update): string
    {
        $value = is_array($update) ? ($update['publication_date'] ?? '') : $update->publication_date;

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return trim((string) $value);
    }

    private static function isTendersOnTime(CountryUpdate|array $update): bool
    {
        $sourceText = Str::lower(implode(' ', [
            self::field($update, 'source_name'),
            self::field($update, 'source_url'),
            self::field($update, 'summary'),
            self::field($update, 'summary_english'),
        ]));

        return Str::contains($sourceText, ['tendersontime', 'tenders on time']);
    }

    private static function field(CountryUpdate|array $update, string $key): string
    {
        if (is_array($update)) {
            return (string) ($update[$key] ?? '');
        }

        return (string) $update->{$key};
    }
}
