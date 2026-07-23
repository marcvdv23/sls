<?php

namespace App\Services;

use App\Models\CountryUpdate;
use App\Models\Journalist;
use App\Models\JournalistArticle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class JournalistDiscoveryService
{
    public function capture(CountryUpdate $update): JournalistArticle
    {
        $update->loadMissing('country');

        $metadata = $this->metadataFromSource((string) $update->source_url);
        $name = $this->cleanName($metadata['author'] ?? '') ?: null;
        $email = $this->cleanEmail($metadata['email'] ?? '') ?: null;
        $publication = trim((string) ($update->source_name ?: ($metadata['publication'] ?? ''))) ?: null;
        $publicationKey = $this->publicationKey($publication, (string) $update->source_url);
        $topics = $this->topicsForUpdate($update);
        $fingerprint = $this->fingerprint($name, $email, $publication, (string) $update->source_url);

        $journalist = $this->findExistingJournalist($name, $email, $publicationKey, $fingerprint)
            ?? new Journalist(['source_fingerprint' => $fingerprint]);

        if (! $journalist->source_fingerprint) {
            $journalist->source_fingerprint = $fingerprint;
        }

        $journalist->fill([
            'name' => $name ?: $journalist->name ?: 'Unknown journalist',
            'name_normalized' => $this->normalizeName($name ?: $journalist->name ?: 'Unknown journalist'),
            'email' => $email ?: $journalist->email,
            'publication_name' => $publication ?: $journalist->publication_name,
            'publication_country_id' => $update->country_id,
            'publication_country_name' => $update->country?->name,
            'topics' => array_values(array_unique(array_merge($journalist->topics ?? [], $topics))),
            'discovery_status' => $name ? ($email ? 'email_found' : 'name_found') : 'needs_review',
            'last_seen_at' => now(),
        ])->save();

        return JournalistArticle::query()->updateOrCreate(
            [
                'journalist_id' => $journalist->id,
                'country_update_id' => $update->id,
            ],
            [
                'article_title' => Str::limit((string) ($update->title_english ?: $update->title), 500, ''),
                'article_url' => Str::limit((string) $update->source_url, 1000, ''),
                'publication_name' => $publication,
                'publication_country_id' => $update->country_id,
                'topics' => $topics,
                'author_name_raw' => $metadata['author'] ?? null,
                'author_email_raw' => $metadata['email'] ?? null,
                'praise_note' => $this->praiseNote($update, $name, $topics),
                'outreach_status' => 'draft',
                'captured_at' => now(),
            ],
        );
    }

    private function metadataFromSource(string $url): array
    {
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            return [];
        }

        try {
            $html = Http::timeout(12)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; 1G-SLS journalist research bot; +http://localhost)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                ->get($url)
                ->body();
        } catch (\Throwable) {
            return [];
        }

        if ($html === '') {
            return [];
        }

        return [
            'author' => $this->extractAuthor($html),
            'email' => $this->extractEmail($html),
            'publication' => $this->extractMeta($html, ['og:site_name', 'application-name']),
        ];
    }

    private function extractAuthor(string $html): ?string
    {
        $meta = $this->extractMeta($html, [
            'author',
            'article:author',
            'parsely-author',
            'sailthru.author',
            'byl',
            'byline',
            'dc.creator',
            'dcterms.creator',
            'cXenseParse:author',
        ]);
        if ($meta) {
            return $meta;
        }

        foreach ($this->jsonLdBlocks($html) as $json) {
            $author = $this->authorFromJson($json);
            if ($author) {
                return $author;
            }
        }

        foreach ([
            '/<a[^>]+rel=["\'][^"\']*author[^"\']*["\'][^>]*>(.*?)<\/a>/is',
            '/<[^>]+itemprop=["\']author["\'][^>]*>(.*?)<\/[^>]+>/is',
            '/<[^>]+class=["\'][^"\']*(?:byline|author|writer|article-author|post-author)[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/is',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $candidate = $this->nameFromBylineText($match[1]);
                if ($candidate) {
                    return $candidate;
                }
            }
        }

        $text = $this->visibleText($html);
        $topAndBottom = Str::substr($text, 0, 12000) . "\n" . Str::substr($text, -8000);

        foreach ([
            '/(?:^|\n)\s*(?:by|written by|reported by|story by|author)\s*:?\s*([\p{Lu}][\p{L} .\'’\-]+(?:\s+[\p{Lu}][\p{L}.\'’\-]+){0,5})\b/u',
            '/(?:^|\n)\s*([\p{Lu}][\p{L}.\'’\-]+(?:\s+[\p{Lu}][\p{L}.\'’\-]+){1,5})\s*(?:\n|\|)\s*(?:Staff Writer|Reporter|Correspondent|Editor)\b/u',
            '/\bby\s+([\p{Lu}][\p{L}.\'’\-]+(?:\s+[\p{Lu}][\p{L}.\'’\-]+){0,5})\b/u',
        ] as $pattern) {
            if (preg_match($pattern, $topAndBottom, $match)) {
                $candidate = $this->cleanName($match[1]);
                if ($this->looksLikePersonName($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function jsonLdBlocks(string $html): array
    {
        if (! preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            return [];
        }

        return array_map(fn (string $json) => html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5), $matches[1]);
    }

    private function authorFromJson(string $json): ?string
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        $queue = [$decoded];
        while ($queue) {
            $node = array_shift($queue);
            if (! is_array($node)) {
                continue;
            }

            if (isset($node['author'])) {
                $author = $node['author'];
                if (is_string($author)) {
                    return $this->cleanName($author);
                }
                if (is_array($author)) {
                    $authorNodes = array_is_list($author) ? $author : [$author];
                    foreach ($authorNodes as $authorNode) {
                        if (is_array($authorNode) && filled($authorNode['name'] ?? null)) {
                            return $this->cleanName((string) $authorNode['name']);
                        }
                    }
                }
            }

            foreach ($node as $value) {
                if (is_array($value)) {
                    $queue[] = $value;
                }
            }
        }

        return null;
    }

    private function nameFromBylineText(string $html): ?string
    {
        $text = $this->visibleText($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/^(by|written by|reported by|story by|author)\s*:?\s*/i', '', trim((string) $text));
        $text = preg_split('/\s*(?:\||•|,\s*(?:staff|reporter|correspondent)|\d{1,2}\s+[A-Z][a-z]+\s+\d{4})\s*/', (string) $text)[0] ?? $text;
        $candidate = $this->cleanName((string) $text);

        return $this->looksLikePersonName($candidate) ? $candidate : null;
    }

    private function visibleText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', (string) $html);
        $html = preg_replace('/<\/?(?:p|div|section|article|header|footer|h[1-6]|br|li|span)\b[^>]*>/i', "\n", (string) $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t\r\f\v]+/', ' ', (string) $text);
        $text = preg_replace('/\n\s+/', "\n", (string) $text);
        $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);

        return trim((string) $text);
    }

    private function looksLikePersonName(?string $name): bool
    {
        $name = trim((string) $name);
        if ($name === '' || Str::length($name) > 80) {
            return false;
        }

        if (Str::contains(Str::lower($name), ['admin', 'editorial', 'staff', 'newsroom', 'press release', 'agency', 'share', 'facebook', 'twitter'])) {
            return false;
        }

        return preg_match('/^[\p{L}][\p{L}.\'’\-]+(?:\s+[\p{L}][\p{L}.\'’\-]+){1,5}$/u', $name) === 1;
    }
    private function extractMeta(string $html, array $names): ?string
    {
        foreach ($names as $name) {
            $quoted = preg_quote($name, '/');
            if (preg_match('/<meta[^>]+(?:name|property)=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $match)) {
                return html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5);
            }
        }

        return null;
    }

    private function extractEmail(string $html): ?string
    {
        if (preg_match('/mailto:([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})/i', $html, $match)) {
            return $match[1];
        }

        if (preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $html, $match)) {
            return $match[0];
        }

        return null;
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5)));
        $name = preg_replace('/^(by|written by|posted by)\s+/i', '', (string) $name);

        return Str::limit($name, 255, '');
    }

    private function cleanEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_VALIDATE_EMAIL) ? trim($email) : '';
    }

    private function normalizeName(string $name): string
    {
        return Str::lower(trim(Str::ascii($name)));
    }

    private function fingerprint(?string $name, ?string $email, ?string $publication, string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $publicationKey = $this->publicationKey($publication, $url);
        $identity = $email || $name
            ? [$email ?: '', $this->normalizeName($name ?: ''), $publicationKey]
            : ['unknown', $publication ?: $host, $url];

        return hash('sha256', Str::lower(implode('|', $identity)));
    }

    private function findExistingJournalist(?string $name, ?string $email, string $publicationKey, string $fingerprint): ?Journalist
    {
        if ($email) {
            $match = Journalist::query()
                ->where('email', $email)
                ->first();

            if ($match) {
                return $match;
            }
        }

        $normalizedName = $this->normalizeName($name ?: '');

        if ($normalizedName !== '') {
            $match = Journalist::query()
                ->where('name_normalized', $normalizedName)
                ->where(function ($query) use ($publicationKey, $fingerprint) {
                    $query
                        ->where('source_fingerprint', $fingerprint)
                        ->orWhere('publication_name', 'like', '%' . $publicationKey . '%');
                })
                ->first();

            if ($match) {
                return $match;
            }

            return Journalist::query()
                ->where('source_fingerprint', $fingerprint)
                ->first();
        }

        return Journalist::query()
            ->where('source_fingerprint', $fingerprint)
            ->first();
    }

    private function publicationKey(?string $publication, string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');
        $host = preg_replace('/^www\./i', '', Str::lower($host));
        $publication = Str::lower(trim((string) $publication));

        return $publication !== '' ? $publication : $host;
    }

    private function topicsForUpdate(CountryUpdate $update): array
    {
        $text = Str::lower($update->title . ' ' . $update->title_english . ' ' . $update->summary);
        $topics = [];

        foreach ([
            'social security' => ['social security', 'social insurance', 'pension', 'provident fund', 'national insurance'],
            'IT systems' => ['software', 'system', 'digital', 'automation', 'platform', 'database', 'technology'],
            'HRMS' => ['hrms', 'hcm', 'payroll', 'human resource'],
            'public finance' => ['tax', 'budget', 'treasury', 'public finance'],
            'procurement' => ['tender', 'procurement', 'request for proposal', 'bidding'],
        ] as $topic => $signals) {
            if (Str::contains($text, $signals)) {
                $topics[] = $topic;
            }
        }

        return $topics ?: ['country intelligence'];
    }

    private function praiseNote(CountryUpdate $update, ?string $name, array $topics): string
    {
        $title = trim((string) ($update->title_english ?: $update->title));
        $topicText = implode(', ', array_slice($topics, 0, 2));
        $greeting = $name ? 'Hi ' . Str::before($name, ' ') . ',' : 'Hello,';

        return $greeting . ' I read your piece "' . Str::limit($title, 140, '') . '" and appreciated the clear way you highlighted the ' . $topicText . ' angle. It is exactly the kind of reporting that helps practitioners understand what is changing on the ground.';
    }
}
