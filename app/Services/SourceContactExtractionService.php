<?php

namespace App\Services;

use App\Models\CountryUpdate;
use App\Models\IntelligenceContact;
use App\Models\IntelligenceDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

class SourceContactExtractionService
{
    /**
     * @return array{documents_checked:int, documents_archived:int, contacts_found:int, contacts_created:int, contacts_updated:int, errors:array<int, string>}
     */
    public function extractForUpdate(CountryUpdate $update): array
    {
        $documentsChecked = 0;
        $documentsArchived = 0;
        $created = 0;
        $updated = 0;
        $errors = [];

        $documents = $this->discoverDocuments($update);

        foreach ($documents as $document) {
            $documentsChecked++;

            try {
                $archivedDocument = $this->archiveDocument($update, $document);
                $documentsArchived++;

                $text = $document['is_pdf']
                    ? $this->extractPdfText($document['body'])
                    : $this->htmlToText($document['body']);

                $structuredContacts = $this->extractStructuredContactsFromDocument($document['body'], $update, $document, $archivedDocument);
                $regexContacts = $structuredContacts->isNotEmpty() && Str::contains(Str::lower($document['url']), 'search.worldbank.org/api/v2/procnotices')
                    ? collect()
                    : $this->extractContactsFromText($text, $update, $document, $archivedDocument);
                $contacts = $regexContacts
                    ->merge($structuredContacts)
                    ->unique(fn (array $contact) => $contact['source_fingerprint'])
                    ->values();

                foreach ($contacts as $contactData) {
                    $contact = IntelligenceContact::updateOrCreate(
                        ['source_fingerprint' => $contactData['source_fingerprint']],
                        $contactData,
                    );

                    $contact->wasRecentlyCreated ? $created++ : $updated++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = Str::limit($document['url'] . ': ' . $exception->getMessage(), 500, '');
            }
        }

        return [
            'documents_checked' => $documentsChecked,
            'documents_archived' => $documentsArchived,
            'contacts_found' => $created + $updated,
            'contacts_created' => $created,
            'contacts_updated' => $updated,
            'errors' => $errors,
        ];
    }

    private function archiveDocument(CountryUpdate $update, array $document): IntelligenceDocument
    {
        $body = (string) $document['body'];
        $hash = hash('sha256', $body);
        $extension = $document['is_pdf'] ? 'pdf' : 'html';
        $storagePath = 'intelligence-documents/' . $update->id . '/' . $hash . '.' . $extension;

        if (! Storage::exists($storagePath)) {
            Storage::put($storagePath, $body);
        }

        return IntelligenceDocument::updateOrCreate(
            [
                'country_update_id' => $update->id,
                'sha256_hash' => $hash,
            ],
            [
                'country_id' => $update->country_id,
                'source_name' => $update->source_name,
                'source_url' => $update->source_url,
                'document_url' => $document['url'],
                'document_title' => Str::limit((string) ($update->title_english ?: $update->title), 500, ''),
                'content_type' => $document['content_type'] ?: ($document['is_pdf'] ? 'application/pdf' : 'text/html'),
                'storage_path' => $storagePath,
                'byte_size' => strlen($body),
                'is_pdf' => (bool) $document['is_pdf'],
                'fetched_at' => now(),
                'extraction_notes' => null,
            ],
        );
    }

    private function discoverDocuments(CountryUpdate $update): Collection
    {
        $sourceUrl = trim((string) $update->source_url);

        if ($sourceUrl === '') {
            return collect();
        }

        $main = $this->fetchUrl($sourceUrl);

        if (! $main) {
            return collect();
        }

        $documents = collect();

        if ($main['is_pdf']) {
            $documents->push($main);
        } else {
            $documents->push($main);

            $pdfUrls = $this->documentLinksFromHtml($main['body'], $sourceUrl);

            foreach ($pdfUrls as $pdfUrl) {
                $pdf = $this->fetchUrl($pdfUrl);

                if ($pdf) {
                    $documents->push($pdf);
                }
            }
        }

        return $documents->unique('url')->values();
    }

    private function fetchUrl(string $url): ?array
    {
        try {
            $response = Http::timeout(45)
                ->retry(2, 400)
                ->withHeaders([
                    'Accept' => 'application/pdf,text/html,application/xhtml+xml,*/*',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36 1G-SLS',
                ])
                ->withoutVerifying()
                ->get($url);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $body = (string) $response->body();
        $contentType = Str::lower((string) $response->header('Content-Type', ''));
        $isPdf = Str::contains($contentType, 'pdf') || Str::startsWith(ltrim($body), '%PDF-');

        if (! $isPdf && Str::contains($contentType, 'multipart/form-data')) {
            $pdfStart = strpos($body, '%PDF-');
            $pdfEnd = strrpos($body, '%%EOF');

            if ($pdfStart !== false) {
                $body = substr($body, $pdfStart, $pdfEnd !== false ? ($pdfEnd + 5 - $pdfStart) : null);
                $isPdf = true;
            }
        }

        return [
            'url' => $url,
            'body' => $body,
            'content_type' => $contentType,
            'is_pdf' => $isPdf,
        ];
    }

    private function documentLinksFromHtml(string $html, string $baseUrl): Collection
    {
        preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches);
        preg_match_all('/(?:Urlvalue|data-url)=["\']([^"\']+)["\']/i', $html, $attributeMatches);
        preg_match_all('/["\'](\/\/search\.worldbank\.org\/api\/[^"\']+)["\']/i', $html, $apiMatches);
        preg_match_all('/["\'](https?:\/\/search\.worldbank\.org\/api\/[^"\']+)["\']/i', $html, $absoluteApiMatches);

        return collect($matches[1] ?? [])
            ->merge($attributeMatches[1] ?? [])
            ->merge($apiMatches[1] ?? [])
            ->merge($absoluteApiMatches[1] ?? [])
            ->map(fn (string $href) => html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5))
            ->filter(fn (string $href) => $href !== '')
            ->filter(fn (string $href) => Str::contains(Str::lower($href), ['.pdf', 'getdocument.aspx', 'download', 'attachment', 'search.worldbank.org/api/']))
            ->map(fn (string $href) => $this->absoluteUrl($href, $baseUrl))
            ->filter()
            ->unique()
            ->take(10)
            ->values();
    }

    private function absoluteUrl(string $href, string $baseUrl): ?string
    {
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

        $path = $parts['path'] ?? '/';
        $directory = Str::beforeLast($path, '/');

        return $prefix . $directory . '/' . $href;
    }

    private function extractPdfText(string $pdfBody): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'sls_pdf_');
        file_put_contents($tempPath, $pdfBody);

        try {
            return trim((new PdfParser())->parseFile($tempPath)->getText());
        } finally {
            @unlink($tempPath);
        }
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return $this->normalizeText($text);
    }

    private function extractContactsFromText(string $text, CountryUpdate $update, array $document, IntelligenceDocument $archivedDocument): Collection
    {
        $clean = $this->normalizeText($text);

        if ($clean === '') {
            return collect();
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $clean, $matches, PREG_OFFSET_CAPTURE);

        return collect($matches[0] ?? [])
            ->map(function (array $match) use ($clean, $update, $document, $archivedDocument) {
                $email = Str::lower(trim($match[0], " \t\n\r\0\x0B.,;:()[]<>"));
                $offset = (int) $match[1];
                $context = $this->contextAround($clean, $offset);

                return [
                    'country_update_id' => $update->id,
                    'intelligence_document_id' => $archivedDocument->id,
                    'country_id' => $update->country_id,
                    'source_name' => $update->source_name,
                    'source_url' => $update->source_url,
                    'document_url' => $document['url'],
                    'document_title' => Str::limit((string) ($update->title_english ?: $update->title), 500, ''),
                    'organization' => $this->inferOrganization($context, $update),
                    'person_name' => $this->inferPersonName($context, $email),
                    'job_title' => $this->inferJobTitle($context),
                    'email' => $email,
                    'context_excerpt' => $context,
                    'source_fingerprint' => hash('sha256', $update->id . '|' . $archivedDocument->id . '|' . $document['url'] . '|' . $email),
                    'extracted_at' => now(),
                ];
            })
            ->filter(fn (array $contact) => ! Str::endsWith($contact['email'], ['.png', '.jpg', '.jpeg', '.gif', '.svg']))
            ->unique(fn (array $contact) => $contact['source_fingerprint'])
            ->values();
    }

    private function extractStructuredContactsFromDocument(string $body, CountryUpdate $update, array $document, IntelligenceDocument $archivedDocument): Collection
    {
        if (! Str::contains(Str::lower($document['url']), 'search.worldbank.org/api/')) {
            return collect();
        }

        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['procnotices']) || ! is_array($data['procnotices'])) {
            return collect();
        }

        return collect($data['procnotices'])
            ->flatMap(function (array $notice) use ($update, $document, $archivedDocument) {
                $organization = $notice['contact_organization'] ?? $notice['agency_name'] ?? $update->source_name;
                $personName = $notice['contact_name'] ?? null;
                $jobTitle = $notice['contact_job_title'] ?? null;
                $noticeText = $this->htmlToText((string) ($notice['notice_text'] ?? ''));
                $baseContext = $this->normalizeText(implode("\n", array_filter([
                    $notice['bid_description'] ?? null,
                    $personName,
                    $jobTitle,
                    $organization,
                    $notice['contact_address'] ?? null,
                    $notice['contact_phone_no'] ?? null,
                ])));

                $primaryEmails = $this->emailsFromText((string) ($notice['contact_email'] ?? ''));
                $primaryContacts = $primaryEmails->map(function (string $email) use ($update, $document, $archivedDocument, $organization, $personName, $jobTitle, $baseContext) {
                    return $this->contactData(
                        $update,
                        $document,
                        $archivedDocument,
                        $email,
                        $organization,
                        $personName,
                        $jobTitle,
                        $baseContext,
                        'worldbank-structured-primary',
                    );
                });

                $additionalContacts = $this->emailsFromText($noticeText)
                    ->reject(fn (string $email) => $primaryEmails->contains($email))
                    ->unique()
                    ->map(function (string $email) use ($update, $document, $archivedDocument, $organization, $jobTitle, $noticeText) {
                        $offset = stripos($noticeText, $email);
                        $context = $offset === false ? $noticeText : $this->contextAround($noticeText, $offset);

                        $inferredTitle = Str::contains(Str::lower($context), ['obtain further information', 'interested eligible proposers'])
                            ? 'Procurement contact'
                            : ($this->inferJobTitle($context) ?: $jobTitle);

                        return $this->contactData(
                            $update,
                            $document,
                            $archivedDocument,
                            $email,
                            $organization,
                            $this->inferPersonName($context, $email),
                            $inferredTitle,
                            $context,
                            'worldbank-notice-text',
                        );
                    });

                return $primaryContacts->merge($additionalContacts);
            })
            ->values();
    }

    private function contactData(
        CountryUpdate $update,
        array $document,
        IntelligenceDocument $archivedDocument,
        string $email,
        ?string $organization,
        ?string $personName,
        ?string $jobTitle,
        string $context,
        string $sourceKind,
    ): array {
        return [
            'country_update_id' => $update->id,
            'intelligence_document_id' => $archivedDocument->id,
            'country_id' => $update->country_id,
            'source_name' => $update->source_name,
            'source_url' => $update->source_url,
            'document_url' => $document['url'],
            'document_title' => Str::limit((string) ($update->title_english ?: $update->title), 500, ''),
            'organization' => $organization ? Str::limit((string) $organization, 255, '') : null,
            'person_name' => $personName ? Str::limit((string) $personName, 255, '') : null,
            'job_title' => $jobTitle ? Str::limit((string) $jobTitle, 255, '') : null,
            'email' => $email,
            'context_excerpt' => Str::limit($context, 1000, ''),
            'source_fingerprint' => hash('sha256', $update->id . '|' . $archivedDocument->id . '|' . $document['url'] . '|' . $sourceKind . '|' . $email),
            'extracted_at' => now(),
        ];
    }

    private function emailsFromText(string $text): Collection
    {
        $text = $this->normalizeText($text);
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn (string $email) => Str::lower(trim($email, " \t\n\r\0\x0B.,;:()[]<>")))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->filter(fn (string $email) => ! Str::endsWith($email, ['.png', '.jpg', '.jpeg', '.gif', '.svg']))
            ->unique()
            ->values();
    }

    private function contextAround(string $text, int $offset): string
    {
        $start = max(0, $offset - 450);
        $length = 900;

        return Str::limit(trim(substr($text, $start, $length)), 1000, '');
    }

    private function inferOrganization(string $context, CountryUpdate $update): ?string
    {
        foreach (preg_split('/\n+/', $context) ?: [] as $line) {
            $line = trim($line);

            if (Str::contains(Str::lower($line), ['ministry', 'department', 'authority', 'agency', 'commission', 'bank', 'office', 'government', 'social security', 'procurement'])) {
                return Str::limit($line, 255, '');
            }
        }

        return $update->source_name ?: $update->country?->name;
    }

    private function inferPersonName(string $context, string $email): ?string
    {
        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\s*,\s*' . preg_quote($email, '/') . '/i', $context, $matches)) {
            return Str::limit(Str::title($matches[1]), 255, '');
        }

        $emailLocal = Str::before($email, '@');
        $candidate = trim(str_replace(['.', '_', '-'], ' ', $emailLocal));

        if (preg_match('/^[a-z]+(?:\s+[a-z]+){1,3}$/i', $candidate)) {
            return Str::title($candidate);
        }

        if (preg_match('/([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\s*(?:\n|,|;|<|>|Email|E-mail)/', $context, $matches)) {
            return Str::limit($matches[1], 255, '');
        }

        return null;
    }

    private function inferJobTitle(string $context): ?string
    {
        foreach (preg_split('/\n+/', $context) ?: [] as $line) {
            $line = trim($line);

            if (Str::contains(Str::lower($line), ['procurement', 'specialist', 'manager', 'director', 'officer', 'consultant', 'coordinator', 'contact'])) {
                return Str::limit($line, 255, '');
            }
        }

        return null;
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(['\\r\\n', '\\n', '\\r', '\\t'], "\n", $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text ?? '');
    }
}
