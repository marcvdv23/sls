<?php

namespace App\Support;

use App\Models\CountryUpdate;
use Illuminate\Support\Str;

class CountryUpdateNoiseRules
{
    public static function isStaticReferenceAggregatorItem(array|CountryUpdate $item): bool
    {
        $sourceName = self::field($item, 'source_name');
        $sourceUrl = self::field($item, 'source_url');

        return self::isNewsAggregatorSource($sourceName)
            && self::isStaticReferenceUrl($sourceUrl);
    }

    public static function isNewsAggregatorSource(?string $sourceName): bool
    {
        $sourceName = Str::lower((string) $sourceName);

        return Str::contains($sourceName, [
            'bing news rss',
            'google news rss',
            'bing news',
            'google news',
        ]);
    }

    public static function isStaticReferenceUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        $path = Str::lower(rawurldecode((string) parse_url($url, PHP_URL_PATH)));
        $path = '/' . ltrim($path, '/');

        if ($host === 'helpage.org' || Str::endsWith($host, '.helpage.org')) {
            return preg_match('~/country[_-]?profiles?(/|$)~', $path) === 1;
        }

        return preg_match('~
            /
            (
                country[_-]?profiles?
                | country/[^/]+/profile
                | countries/[^/]+/profile
                | profile/country
            )
            (/|$)
        ~x', $path) === 1;
    }

    private static function field(array|CountryUpdate $item, string $key): string
    {
        if (is_array($item)) {
            return (string) ($item[$key] ?? '');
        }

        return (string) $item->{$key};
    }
}
