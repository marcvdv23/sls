<?php

namespace App\Services;

use App\Models\UniversitySurveyContact;
use App\Models\UniversitySurveyTarget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class UniversitySurveyCrawlerService
{
    /**
     * @return array{created:int, updated:int}
     */
    public function importTargets(string $text): array
    {
        $created = 0;
        $updated = 0;

        collect(preg_split('/\r\n|\r|\n/', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->each(function (string $line) use (&$created, &$updated) {
                $targetData = $this->parseImportLine($line);

                if (! $targetData) {
                    return;
                }

                $target = UniversitySurveyTarget::updateOrCreate(
                    ['website_fingerprint' => $targetData['website_fingerprint']],
                    $targetData,
                );

                $target->wasRecentlyCreated ? $created++ : $updated++;
            });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @return array{pages_checked:int, contacts_found:int, patterns:array<int, string>, errors:array<int, string>}
     */
    public function crawlTarget(UniversitySurveyTarget $target, int $pageLimit = 12): array
    {
        $pagesChecked = 0;
        $contactsFound = 0;
        $errors = [];
        $emails = collect();

        try {
            $pages = $this->candidatePages($target, $pageLimit);

            foreach ($pages as $url) {
                $page = $this->fetchPage($url);

                if (! $page) {
                    continue;
                }

                $pagesChecked++;
                $text = $this->htmlToText($page['body']);
                $pageEmails = $this->extractEmails($text);
                $emails = $emails->merge($pageEmails);

                foreach ($this->contactsFromPage($target, $url, $text, $pageEmails) as $contactData) {
                    $contact = UniversitySurveyContact::updateOrCreate(
                        ['source_fingerprint' => $contactData['source_fingerprint']],
                        $contactData,
                    );

                    $contactsFound++;
                }
            }

            $patterns = $this->publishedEmailPatterns($emails);

            $target->update([
                'status' => 'crawled',
                'last_crawled_at' => now(),
                'next_crawl_at' => now()->addDays(30),
                'pages_checked' => $pagesChecked,
                'contacts_found' => $target->contacts()->count(),
                'published_email_patterns' => $patterns,
                'last_error' => null,
            ]);

            return [
                'pages_checked' => $pagesChecked,
                'contacts_found' => $contactsFound,
                'patterns' => $patterns,
                'errors' => $errors,
            ];
        } catch (Throwable $exception) {
            report($exception);

            $target->update([
                'status' => 'error',
                'last_crawled_at' => now(),
                'last_error' => Str::limit($exception->getMessage(), 1000, ''),
            ]);

            return [
                'pages_checked' => $pagesChecked,
                'contacts_found' => $contactsFound,
                'patterns' => $this->publishedEmailPatterns($emails),
                'errors' => [Str::limit($exception->getMessage(), 500, '')],
            ];
        }
    }

    private function parseImportLine(string $line): ?array
    {
        $parts = str_getcsv($line);
        $url = collect($parts)->first(fn (string $part) => Str::contains($part, ['http://', 'https://', 'www.']));

        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $url = $this->normalizeUrl($url);
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $name = trim((string) ($parts[0] ?? ''));

        if (Str::contains($name, ['http://', 'https://', 'www.']) || $name === '') {
            $name = Str::title(str_replace(['-', '.'], ' ', Str::before($host, '.')));
        }

        $country = trim((string) (collect($parts)
            ->reject(fn (string $part) => $part === $name || $part === $url || Str::contains($part, ['http://', 'https://', 'www.']))
            ->first() ?? ''));

        return [
            'name' => Str::limit($name, 255, ''),
            'country' => $country !== '' ? Str::limit($country, 255, '') : null,
            'website_url' => $url,
            'website_fingerprint' => hash('sha256', Str::lower($url)),
            'domain' => Str::of($host)->lower()->replace('www.', '')->toString(),
            'status' => 'pending',
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    private function candidatePages(UniversitySurveyTarget $target, int $pageLimit): Collection
    {
        $homeUrl = $target->website_url;
        $home = $this->fetchPage($homeUrl);
        $links = collect([$homeUrl]);

        if ($home) {
            preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $home['body'], $matches, PREG_SET_ORDER);

            $links = $links->merge(collect($matches)
                ->map(function (array $match) use ($homeUrl) {
                    $href = html_entity_decode(trim((string) ($match[1] ?? '')), ENT_QUOTES | ENT_HTML5);
                    $label = $this->plainText((string) ($match[2] ?? ''));
                    $url = $this->absoluteUrl($href, $homeUrl);

                    return $url ? ['url' => $url, 'label' => $label] : null;
                })
                ->filter()
                ->filter(fn (array $link) => $this->sameSite((string) $link['url'], $homeUrl))
                ->sortByDesc(fn (array $link) => $this->linkPriority((string) $link['url'], (string) $link['label']))
                ->pluck('url'));
        }

        $guesses = collect([
            '/about',
            '/about-us',
            '/administration',
            '/leadership',
            '/governance',
            '/directory',
            '/staff',
            '/contact',
            '/human-resources',
            '/hr',
            '/information-technology',
            '/it',
            '/ict',
            '/cio',
        ])->map(fn (string $path) => rtrim($homeUrl, '/') . $path);

        return $links
            ->merge($guesses)
            ->filter()
            ->unique()
            ->take($pageLimit)
            ->values();
    }

    private function linkPriority(string $url, string $label): int
    {
        $text = Str::lower($url . ' ' . $label);
        $score = 0;

        foreach ([
            'leadership' => 50,
            'administration' => 45,
            'directory' => 42,
            'staff' => 38,
            'human resources' => 55,
            'hr' => 36,
            'information technology' => 55,
            'ict' => 45,
            'cio' => 45,
            'contact' => 30,
            'governance' => 25,
            'about' => 15,
        ] as $needle => $points) {
            if (Str::contains($text, $needle)) {
                $score += $points;
            }
        }

        return $score;
    }

    private function fetchPage(string $url): ?array
    {
        try {
            $response = Http::timeout(18)
                ->connectTimeout(5)
                ->retry(1, 400)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,*/*',
                    'User-Agent' => 'Mozilla/5.0 1G-SLS Survey Contact Discovery',
                ])
                ->withoutVerifying()
                ->get($url);
        } catch (Throwable) {
            return null;
        }

        if (! $response->ok() || ! Str::contains(Str::lower((string) $response->header('Content-Type')), ['html', 'text/plain', ''])) {
            return null;
        }

        return [
            'url' => $url,
            'body' => (string) $response->body(),
        ];
    }

    private function contactsFromPage(UniversitySurveyTarget $target, string $url, string $text, Collection $emails): Collection
    {
        $contacts = collect();

        foreach ($emails as $email) {
            $offset = stripos($text, $email);
            $context = $this->contextAround($text, $offset === false ? 0 : $offset);
            $roleCategory = $this->roleCategory($context);

            $contacts->push($this->contactData(
                $target,
                $url,
                $roleCategory,
                $this->inferPersonName($context, $email),
                $this->inferJobTitle($context),
                $email,
                $this->emailStatus($email, $context),
                $context,
                $roleCategory === 'general' ? 0.45 : 0.75,
            ));
        }

        foreach ($this->leadershipLines($text) as $line) {
            $contacts->push($this->contactData(
                $target,
                $url,
                $this->roleCategory($line),
                $this->inferPersonName($line, ''),
                $this->inferJobTitle($line),
                null,
                'no_published_email',
                $line,
                0.45,
            ));
        }

        return $contacts
            ->filter(fn (array $contact) => $contact['email'] || $contact['person_name'] || $contact['job_title'])
            ->unique(fn (array $contact) => ($contact['email'] ?: '') . '|' . ($contact['person_name'] ?: '') . '|' . ($contact['job_title'] ?: '') . '|' . $contact['source_url'])
            ->values();
    }

    private function contactData(UniversitySurveyTarget $target, string $url, string $roleCategory, ?string $personName, ?string $jobTitle, ?string $email, string $emailStatus, string $context, float $confidence): array
    {
        return [
            'university_survey_target_id' => $target->id,
            'role_category' => $roleCategory,
            'person_name' => $personName ? Str::limit($personName, 255, '') : null,
            'job_title' => $jobTitle ? Str::limit($jobTitle, 255, '') : null,
            'email' => $email ? Str::lower($email) : null,
            'email_status' => $emailStatus,
            'organization' => $target->name,
            'source_url' => Str::limit($url, 1000, ''),
            'context_excerpt' => Str::limit($context, 1200, ''),
            'confidence_score' => $confidence,
            'source_fingerprint' => hash('sha256', $target->id . '|' . $url . '|' . $roleCategory . '|' . ($personName ?: '') . '|' . ($jobTitle ?: '') . '|' . ($email ?: '') . '|' . Str::limit($context, 180, '')),
            'found_at' => now(),
        ];
    }

    private function leadershipLines(string $text): Collection
    {
        return collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter(fn (string $line) => strlen($line) >= 12 && strlen($line) <= 260)
            ->filter(fn (string $line) => Str::contains(Str::lower($line), [
                'president',
                'vice chancellor',
                'vice-chancellor',
                'chancellor',
                'provost',
                'rector',
                'registrar',
                'chief information officer',
                'director of information',
                'director, information',
                'information technology',
                'human resources',
                'director of hr',
                'hr director',
                'chief human resources',
            ]))
            ->take(40)
            ->values();
    }

    private function roleCategory(string $context): string
    {
        $text = Str::lower($context);

        if (Str::contains($text, ['human resources', 'director of hr', 'hr director', 'chief human resources', 'personnel'])) {
            return 'hr';
        }

        if (Str::contains($text, ['information technology', 'chief information officer', ' cio', 'ict', 'it director', 'technology services'])) {
            return 'it';
        }

        if (Str::contains($text, ['president', 'vice chancellor', 'vice-chancellor', 'chancellor', 'provost', 'rector', 'registrar', 'administration'])) {
            return 'leadership';
        }

        return 'general';
    }

    private function emailStatus(string $email, string $context): string
    {
        $local = Str::before($email, '@');

        if (preg_match('/^[a-z][a-z.\-_]+[.][a-z][a-z.\-_]+$/i', $local) || Str::contains($context, ['@'])) {
            return 'published';
        }

        if (Str::contains($local, ['info', 'contact', 'hr', 'humanresources', 'it', 'ict', 'admissions', 'registry', 'admin'])) {
            return 'role_or_general_published';
        }

        return 'published';
    }

    private function extractEmails(string $text): Collection
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email) => Str::lower(trim($email, " \t\n\r\0\x0B.,;:()[]<>")))
            ->filter(fn (string $email) => ! Str::endsWith($email, ['.png', '.jpg', '.jpeg', '.gif', '.svg']))
            ->unique()
            ->values();
    }

    private function publishedEmailPatterns(Collection $emails): array
    {
        return $emails
            ->map(function (string $email) {
                $local = Str::before($email, '@');
                $domain = Str::after($email, '@');

                if (preg_match('/^[a-z]+[.][a-z]+$/', $local)) {
                    return 'first.last@' . $domain;
                }

                if (preg_match('/^[a-z][a-z]+$/', $local)) {
                    return 'name@' . $domain;
                }

                if (preg_match('/^[a-z][.][a-z]+$/', $local)) {
                    return 'first_initial.last@' . $domain;
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function inferPersonName(string $context, string $email): ?string
    {
        if ($email !== '') {
            $local = Str::before($email, '@');

            if (preg_match('/^[a-z]+[._-][a-z]+$/i', $local)) {
                return Str::title(str_replace(['.', '_', '-'], ' ', $local));
            }
        }

        if (preg_match('/\b(?:Dr\.?|Prof\.?|Professor|Mr\.?|Ms\.?|Mrs\.?)\s+([A-Z][a-zA-Z\'-]+(?:\s+[A-Z][a-zA-Z\'-]+){1,3})\b/', $context, $match)) {
            return $match[1];
        }

        if (preg_match('/\b([A-Z][a-zA-Z\'-]+(?:\s+[A-Z][a-zA-Z\'-]+){1,3})\b\s*(?:,|-|--|:)?\s*(?:President|Vice Chancellor|Vice-Chancellor|Chancellor|Provost|Rector|Registrar|Director|Chief)/', $context, $match)) {
            return $match[1];
        }

        return null;
    }

    private function inferJobTitle(string $context): ?string
    {
        foreach (preg_split('/\n+/', $context) ?: [] as $line) {
            $line = trim($line);

            if (Str::contains(Str::lower($line), [
                'president',
                'vice chancellor',
                'vice-chancellor',
                'chancellor',
                'provost',
                'rector',
                'registrar',
                'director',
                'chief',
                'human resources',
                'information technology',
                'ict',
            ])) {
                return $line;
            }
        }

        return null;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|div|li|tr|td|h[1-6])>/i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5);

        return $this->normalizeText($text);
    }

    private function plainText(string $html): string
    {
        return $this->normalizeText(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    private function contextAround(string $text, int $offset): string
    {
        $start = max(0, $offset - 500);

        return Str::limit(trim(substr($text, $start, 1100)), 1200, '');
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n[ \t]+/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim((string) $text);
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
        if ($href === '' || Str::startsWith($href, ['mailto:', 'tel:', '#', 'javascript:'])) {
            return null;
        }

        if (Str::startsWith($href, ['http://', 'https://'])) {
            return $href;
        }

        $parts = parse_url($baseUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (Str::startsWith($href, '//')) {
            return $parts['scheme'] . ':' . $href;
        }

        $prefix = $parts['scheme'] . '://' . $parts['host'];

        if (Str::startsWith($href, '/')) {
            return $prefix . $href;
        }

        $directory = Str::beforeLast($parts['path'] ?? '/', '/');

        return $prefix . $directory . '/' . $href;
    }

    private function sameSite(string $url, string $baseUrl): bool
    {
        $urlHost = Str::of(parse_url($url, PHP_URL_HOST) ?: '')->lower()->replace('www.', '')->toString();
        $baseHost = Str::of(parse_url($baseUrl, PHP_URL_HOST) ?: '')->lower()->replace('www.', '')->toString();

        return $urlHost !== '' && $baseHost !== '' && ($urlHost === $baseHost || Str::endsWith($urlHost, '.' . $baseHost));
    }
}
