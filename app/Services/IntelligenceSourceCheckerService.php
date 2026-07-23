<?php

namespace App\Services;

use App\Models\IntelligenceSource;
use App\Models\IntelligenceSourceAudit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class IntelligenceSourceCheckerService
{
    /**
     * @return array{ok:bool,status:?int,items_found:int,error:?string}
     */
    public function check(IntelligenceSource $source): array
    {
        $url = $this->sourceUrl($source);
        $domain = $this->normalizedDomain((string) ($source->domain ?: parse_url($url, PHP_URL_HOST)));
        $focus = $this->auditFocus($source);

        if ($url === '') {
            return $this->finish($source, $domain, $focus, 'GET', '', null, null, false, 0, [
                'result' => 'No URL or domain is configured for this source.',
            ], 'No URL or domain configured.');
        }

        try {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->withOptions(['verify' => $this->verifyOption()])
                ->withUserAgent('1G-SLS Source Checker/1.0')
                ->accept('*/*')
                ->get($url);
        } catch (Throwable $exception) {
            return $this->finish($source, $domain, $focus, 'GET', $url, null, null, false, 0, [
                'result' => 'HTTP request failed before a response was received.',
                'exception' => $exception->getMessage(),
            ], $exception->getMessage());
        }

        $body = (string) $response->body();
        $status = $response->status();

        if (! $response->ok()) {
            $registrationStatus = in_array($status, [401, 403], true) ? 'required' : ($source->registration_status ?: 'unknown');
            $source->update([
                'last_checked_at' => now(),
                'last_error' => 'HTTP status ' . $status,
                'registration_status' => $registrationStatus,
                'registration_notes' => in_array($status, [401, 403], true)
                    ? 'The source responded with HTTP ' . $status . '. It may require registration, allow-listing, login, or a custom connector.'
                    : $source->registration_notes,
                'updated_at' => now(),
            ]);

            return $this->finish($source, $domain, $focus, 'GET', $url, null, $status, false, 0, [
                'result' => 'The source was reachable but did not return a usable public page.',
                'http_status' => $status,
                'body_excerpt' => Str::limit($this->plainText($body), 5000, ''),
            ], 'HTTP status ' . $status);
        }

        $contentType = strtolower((string) $response->header('content-type', ''));
        $inspection = Str::contains($contentType, ['xml', 'rss', 'atom']) || preg_match('/^\s*<\?xml|<rss|<feed/i', $body)
            ? $this->inspectFeed($body, $url, $source)
            : $this->inspectHtml($body, $url, $source);

        $itemsFound = count($inspection['matched_links']) + count($inspection['feeds']);
        $ok = true;
        $error = $itemsFound > 0 ? null : 'Reachable, but no feeds or keyword-matched useful links were found.';

        $source->update([
            'last_checked_at' => now(),
            'last_success_at' => $ok ? now() : $source->last_success_at,
            'last_error' => $error,
            'access_method' => $this->updatedAccessMethod($source, $inspection),
            'connector' => $source->connector ?: 'site_crawler',
            'registration_status' => in_array($source->registration_status, ['', 'unknown'], true) ? 'none' : $source->registration_status,
            'force_next_at' => null,
            'updated_at' => now(),
        ]);

        return $this->finish($source, $domain, $focus, 'GET', $url, null, $status, $ok, $itemsFound, [
            'result' => $itemsFound > 0
                ? 'Source is reachable and useful links or feeds were found.'
                : 'Source is reachable, but the generic checker did not find useful links yet.',
            'content_type' => $contentType,
            'page_title' => $inspection['title'],
            'feeds' => $inspection['feeds'],
            'matched_keywords' => $inspection['matched_keywords'],
            'matched_links' => $inspection['matched_links'],
            'sample_text' => Str::limit($this->plainText($body), 3000, ''),
        ], $error);
    }

    public function checkUnknownSources(int $limit = 25, ?string $sourceClass = null): array
    {
        $query = IntelligenceSource::query()
            ->where('is_enabled', true)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhere('registration_status', 'unknown')
                    ->orWhereNull('access_method')
                    ->orWhere('access_method', '');
            })
            ->orderByRaw('last_checked_at IS NULL DESC')
            ->orderBy('id');

        if ($sourceClass) {
            $query->where('source_class', $sourceClass);
        }

        return $query
            ->limit(max(1, min(200, $limit)))
            ->get()
            ->map(fn (IntelligenceSource $source) => [
                'source_id' => $source->id,
                'source_name' => $source->name,
                ...$this->check($source),
            ])
            ->all();
    }

    private function inspectFeed(string $body, string $baseUrl, IntelligenceSource $source): array
    {
        $links = collect();
        $keywords = $this->keywords($source);

        foreach ($this->matchAll('/<item\b.*?<\/item>|<entry\b.*?<\/entry>/is', $body) as $itemXml) {
            $title = $this->firstTagValue($itemXml, ['title']);
            $url = $this->firstTagValue($itemXml, ['link', 'guid']);
            $text = Str::lower($this->plainText($title . ' ' . $itemXml));

            if (Str::contains($text, $keywords)) {
                $links->push([
                    'title' => Str::limit($this->plainText($title ?: $url), 180, ''),
                    'url' => Str::limit($this->absoluteUrl($url, $baseUrl), 500, ''),
                    'matched_terms' => $this->matchedTerms($text, $keywords),
                ]);
            }
        }

        return [
            'title' => 'RSS/XML feed',
            'feeds' => [[
                'title' => 'Configured feed',
                'url' => $baseUrl,
            ]],
            'matched_keywords' => $this->matchedTerms(Str::lower($this->plainText($body)), $keywords),
            'matched_links' => $links->unique('url')->take(20)->values()->all(),
        ];
    }

    private function inspectHtml(string $body, string $baseUrl, IntelligenceSource $source): array
    {
        $title = $this->pageTitle($body);
        $keywords = $this->keywords($source);
        $plainText = Str::lower($this->plainText($body));
        $feeds = collect();
        $matchedLinks = collect();

        foreach ($this->extractLinks($body, $baseUrl) as $link) {
            $linkText = Str::lower($this->plainText(($link['title'] ?? '') . ' ' . ($link['url'] ?? '')));
            $type = strtolower((string) ($link['type'] ?? ''));

            if (Str::contains($type, ['rss', 'atom']) || Str::contains($linkText, ['rss', 'feed', '.xml', 'atom'])) {
                $feeds->push([
                    'title' => Str::limit($link['title'] ?: 'Feed link', 180, ''),
                    'url' => $link['url'],
                ]);
            }

            if (Str::contains($linkText, $keywords) || Str::contains($linkText, $this->navigationTerms($source))) {
                $matchedLinks->push([
                    'title' => Str::limit($link['title'] ?: $link['url'], 180, ''),
                    'url' => Str::limit($link['url'], 500, ''),
                    'matched_terms' => $this->matchedTerms($linkText, array_merge($keywords, $this->navigationTerms($source))),
                ]);
            }
        }

        return [
            'title' => $title,
            'feeds' => $feeds->unique('url')->take(10)->values()->all(),
            'matched_keywords' => $this->matchedTerms($plainText, $keywords),
            'matched_links' => $matchedLinks->unique('url')->take(30)->values()->all(),
        ];
    }

    private function extractLinks(string $html, string $baseUrl): array
    {
        $links = [];

        foreach ($this->matchAll('/<link\b[^>]*>/i', $html) as $match) {
            $tag = $match[0] ?? '';
            $href = $this->attribute($tag, 'href');

            if ($href) {
                $links[] = [
                    'title' => $this->attribute($tag, 'title') ?: $this->attribute($tag, 'rel') ?: 'Feed',
                    'url' => $this->absoluteUrl($href, $baseUrl),
                    'type' => $this->attribute($tag, 'type'),
                ];
            }
        }

        foreach ($this->matchAll('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html) as $match) {
            $links[] = [
                'title' => $this->plainText($match[2] ?? ''),
                'url' => $this->absoluteUrl($match[1] ?? '', $baseUrl),
                'type' => '',
            ];
        }

        return collect($links)
            ->filter(fn (array $link) => filled($link['url'] ?? ''))
            ->values()
            ->all();
    }

    private function sourceUrl(IntelligenceSource $source): string
    {
        $url = trim((string) $source->url);

        if ($url !== '') {
            return Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://' . ltrim($url, '/');
        }

        $domain = trim((string) $source->domain);

        return $domain === '' ? '' : 'https://' . ltrim($domain, '/');
    }

    private function updatedAccessMethod(IntelligenceSource $source, array $inspection): string
    {
        if (filled($source->access_method) && ! in_array($source->access_method, ['unknown', 'manual_review'], true)) {
            return (string) $source->access_method;
        }

        return count($inspection['feeds'] ?? []) > 0 ? 'rss_detected' : 'basic_crawler';
    }

    private function auditFocus(IntelligenceSource $source): string
    {
        return match ($source->focus) {
            'tenders' => 'tenders',
            'news' => 'news',
            'both' => 'both',
            default => (string) ($source->focus ?: 'source_check'),
        };
    }

    private function keywords(IntelligenceSource $source): array
    {
        $focusKeys = match ($source->focus) {
            'hrms_tenders' => ['hrms_tenders'],
            'erms_tenders' => ['erms_tenders'],
            'ebpc_tenders' => ['ebpc_tenders'],
            'sector_tenders' => ['sector_tenders'],
            'social_security', 'news' => ['social_security'],
            'tenders' => ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'],
            default => ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders', 'sector_tenders'],
        };

        return collect($focusKeys)
            ->flatMap(fn (string $focus) => config("country_intelligence.focuses.$focus.terms", []))
            ->merge($this->navigationTerms($source))
            ->map(fn (string $term) => Str::lower(trim($term)))
            ->filter(fn (string $term) => strlen($term) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    private function navigationTerms(IntelligenceSource $source): array
    {
        return match ($source->source_class) {
            'central_tender_portal', 'donor_tender_portal' => ['tender', 'procurement', 'rfp', 'bid', 'opportunities', 'contract notices', 'solicitations'],
            'social_security_admin', 'government' => ['news', 'press release', 'announcements', 'notices', 'procurement', 'tenders'],
            default => ['news', 'press release', 'announcements', 'notices', 'procurement', 'tenders'],
        };
    }

    private function finish(IntelligenceSource $source, string $domain, string $focus, string $method, string $url, ?string $payload, ?int $status, bool $ok, int $itemsFound, array $response, ?string $error): array
    {
        if (Schema::hasTable('intelligence_source_audits')) {
            IntelligenceSourceAudit::query()->create([
                'intelligence_source_id' => $source->id,
                'source_name' => Str::limit($source->name, 255, ''),
                'domain' => Str::limit($domain, 255, ''),
                'focus' => Str::limit($focus, 80, ''),
                'method' => $method,
                'request_url' => Str::limit($url, 4000, ''),
                'request_payload' => $payload,
                'http_status' => $status,
                'ok' => $ok,
                'items_found' => max(0, $itemsFound),
                'response_excerpt' => Str::limit(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '', 12000, ''),
                'error_message' => $error,
                'checked_at' => now(),
            ]);
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'items_found' => max(0, $itemsFound),
            'error' => $error,
        ];
    }

    private function verifyOption(): bool|string
    {
        $sourceVerify = env('INTELLIGENCE_SOURCE_VERIFY_SSL');

        if ($sourceVerify !== null && filter_var($sourceVerify, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false) {
            return false;
        }

        if (filter_var(env('SERPAPI_VERIFY_SSL'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false) {
            return false;
        }

        $bundle = env('INTELLIGENCE_SOURCE_CA_BUNDLE') ?: env('SERPAPI_CA_BUNDLE');

        if (is_string($bundle) && $bundle !== '' && file_exists($bundle)) {
            return $bundle;
        }

        return true;
    }

    private function pageTitle(string $html): string
    {
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $match)) {
            return Str::limit($this->plainText($match[1]), 180, '');
        }

        return 'No page title detected';
    }

    private function firstTagValue(string $xml, array $tags): string
    {
        foreach ($tags as $tag) {
            if (preg_match('/<' . preg_quote($tag, '/') . '\b[^>]*>(.*?)<\/' . preg_quote($tag, '/') . '>/is', $xml, $match)) {
                return $this->plainText($match[1]);
            }

            if ($tag === 'link' && preg_match('/<link\b[^>]*href=["\']([^"\']+)["\']/i', $xml, $match)) {
                return $match[1];
            }
        }

        return '';
    }

    private function attribute(string $tag, string $attribute): string
    {
        return preg_match('/\b' . preg_quote($attribute, '/') . '=["\']([^"\']+)["\']/i', $tag, $match)
            ? html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
    }

    /**
     * @return array<int, mixed>
     */
    private function matchAll(string $pattern, string $value): array
    {
        preg_match_all($pattern, $value, $matches, PREG_SET_ORDER);

        return $matches ?: [];
    }

    private function matchedTerms(string $text, array $terms): array
    {
        return collect($terms)
            ->filter(fn (string $term) => $term !== '' && Str::contains($text, $term))
            ->take(15)
            ->values()
            ->all();
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($url === '' || Str::startsWith($url, ['mailto:', 'tel:', 'javascript:', '#'])) {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';

        if ($host === '') {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            return $scheme . ':' . $url;
        }

        if (Str::startsWith($url, '/')) {
            return $scheme . '://' . $host . $url;
        }

        $path = $parts['path'] ?? '/';
        $directory = rtrim(Str::beforeLast($path, '/'), '/');

        return $scheme . '://' . $host . ($directory ? '/' . ltrim($directory, '/') : '') . '/' . ltrim($url, '/');
    }

    private function normalizedDomain(string $domain): string
    {
        return Str::of($domain)
            ->lower()
            ->replace('www.', '')
            ->replaceStart('http://', '')
            ->replaceStart('https://', '')
            ->before('/')
            ->trim()
            ->toString();
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
