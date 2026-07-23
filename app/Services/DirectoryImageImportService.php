<?php

namespace App\Services;

use App\Models\DirectoryImageBatch;
use App\Models\DirectoryImageEntry;
use App\Models\DirectoryImagePage;
use App\Models\MarketOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DirectoryImageImportService
{
    /**
     * @param array<int, UploadedFile> $files
     */
    public function createBatch(array $files, array $data): DirectoryImageBatch
    {
        $batch = DirectoryImageBatch::create([
            'title' => $data['title'] ?: 'Directory image import ' . now('America/Chicago')->format('Y-m-d H:i'),
            'organization_type' => $data['organization_type'],
            'industry' => $data['industry'] ?? null,
            'organization_subcategory' => $data['organization_subcategory'] ?? null,
            'market_crawler_id' => $data['market_crawler_id'] ?? null,
            'status' => 'uploaded',
            'image_count' => count($files),
            'notes' => $data['notes'] ?? null,
        ]);

        usort($files, fn (UploadedFile $left, UploadedFile $right) => $this->naturalFileOrder($left->getClientOriginalName()) <=> $this->naturalFileOrder($right->getClientOriginalName()));

        foreach ($files as $index => $file) {
            $path = $file->store('directory-images/batch-' . $batch->id);
            $batch->pages()->create([
                'original_filename' => $file->getClientOriginalName(),
                'storage_path' => $path,
                'sort_order' => $index + 1,
                'status' => 'uploaded',
            ]);
        }

        return $batch;
    }

    public function process(DirectoryImageBatch $batch): array
    {
        set_time_limit(0);

        $tesseract = $this->optionalExecutablePath('tesseract');

        if (! $tesseract) {
            throw new RuntimeException('Tesseract OCR is not available. Configure TESSERACT_PATH or install tesseract.');
        }

        $batch->entries()->delete();
        $pageCount = 0;
        $entryCount = 0;
        $lastDraftEntry = null;

        $batch->pages()->orderBy('sort_order')->orderBy('original_filename')->each(function (DirectoryImagePage $page) use ($tesseract, &$pageCount, &$entryCount, &$lastDraftEntry) {
            $absolutePath = Storage::path($page->storage_path);

            try {
                $texts = [];

                foreach ($this->ocrImagesForPath($absolutePath) as $index => $ocrPath) {
                    $process = new Process([$tesseract, $ocrPath, 'stdout', '-l', 'eng', '--psm', '6', '--dpi', '300', '-c', 'preserve_interword_spaces=1']);
                    $process->setTimeout(180);
                    $process->run();

                    if ($ocrPath !== $absolutePath && is_file($ocrPath)) {
                        @unlink($ocrPath);
                    }

                    if (! $process->isSuccessful()) {
                        throw new RuntimeException(Str::limit($process->getErrorOutput() ?: $process->getOutput(), 1000));
                    }

                    $texts[] = '--- OCR COLUMN ' . ($index + 1) . ' ---' . "\n" . $process->getOutput();
                }

                $text = $this->cleanOcrText(implode("\n\n", $texts));
                $page->update([
                    'ocr_text' => $text,
                    'status' => 'ocr_complete',
                    'last_error' => null,
                ]);

                foreach ($this->parseEntries($text) as $entry) {
                    $isContinuation = (bool) ($entry['_is_continuation'] ?? false);
                    unset($entry['_is_continuation']);

                    if ($isContinuation && $lastDraftEntry) {
                        $this->mergeContinuationIntoEntry($lastDraftEntry, $entry);
                        continue;
                    }

                    $draftEntry = $page->entries()->create($entry + [
                        'directory_image_batch_id' => $page->directory_image_batch_id,
                        'review_status' => 'needs_review',
                    ]);

                    if (! $isContinuation) {
                        $lastDraftEntry = $draftEntry;
                    }

                    $entryCount++;
                }

                $pageCount++;
            } catch (\Throwable $exception) {
                $page->update([
                    'status' => 'error',
                    'last_error' => Str::limit($exception->getMessage(), 2000),
                ]);
            }
        });

        $batch->update([
            'status' => 'processed',
            'entry_count' => $entryCount,
        ]);

        return [
            'pages_processed' => $pageCount,
            'entries_found' => $entryCount,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parseEntries(string $text): Collection
    {
        $lines = collect(preg_split('/\R+/', $text) ?: [])
            ->map(fn (string $line) => trim(preg_replace('/\s{2,}/', ' ', $line) ?? $line))
            ->filter()
            ->values();

        $blocks = collect();
        $current = [];

        foreach ($lines as $line) {
            if (Str::startsWith($line, '--- OCR COLUMN')) {
                if ($current !== []) {
                    $blocks->push($current);
                    $current = [];
                }

                continue;
            }

            if ($this->isLikelyOrganizationHeading($line) && $current !== [] && ! $this->hasOrganizationHeading($current)) {
                $blocks->push($current);
                $current = [$line];
                continue;
            }

            if ($this->isLikelyOrganizationHeading($line) && $this->hasOrganizationHeading($current) && ! $this->endsWithOnlyOrganizationHeadings($current)) {
                $blocks->push($current);
                $current = [$line];
                continue;
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $blocks->push($current);
        }

        return $blocks
            ->map(fn (array $block) => $this->entryFromBlock($block))
            ->filter(fn (array $entry) => filled($entry['organization_name']))
            ->values();
    }

    public function importEntry(DirectoryImageEntry $entry, array $data = []): MarketOrganization
    {
        $organizationName = $data['organization_name'] ?? $entry->organization_name;
        $country = $data['country_normalized'] ?? $entry->country_normalized ?? $entry->country_raw ?? 'Country pending';
        $website = $data['website'] ?? $entry->website;
        $email = array_key_exists('email', $data) ? $this->normalizeEmail($data['email']) : $entry->email;
        $phone = array_key_exists('phone', $data) ? trim((string) $data['phone']) : $entry->phone;
        $domain = $website ? parse_url(Str::startsWith($website, ['http://', 'https://']) ? $website : 'https://' . $website, PHP_URL_HOST) : null;
        $fingerprint = hash('sha256', Str::lower($organizationName . '|' . $country . '|' . ($domain ?? '')));

        $organization = MarketOrganization::updateOrCreate(
            ['source_fingerprint' => $fingerprint],
            [
                'name' => Str::limit($organizationName, 255, ''),
                'name_normalized' => Str::lower(Str::limit($organizationName, 500, '')),
                'organization_type' => $entry->batch->organization_type,
                'industry' => $entry->batch->industry,
                'organization_subcategory' => $entry->batch->organization_subcategory,
                'country' => $country,
                'country_raw' => $entry->country_raw,
                'country_iso' => $entry->country_iso,
                'country_resolution_status' => $entry->country_iso || $country !== 'Country pending' ? 'resolved' : 'missing',
                'website_url' => $website ? (Str::startsWith($website, ['http://', 'https://']) ? $website : 'https://' . $website) : null,
                'website_domain' => $domain ? Str::of($domain)->lower()->replace('www.', '')->toString() : null,
                'organization_phone' => $phone ?: null,
                'status' => 'active',
                'lead_status' => 'unqualified',
                'lead_source' => 'directory_image_import',
                'notes' => trim('Imported from directory image OCR.' . "\n" . ($entry->address_text ?: '') . "\n" . ($entry->executive_text ?: '')),
                'source_fingerprint' => $fingerprint,
            ],
        );

        if ($email) {
            $organization->contacts()->updateOrCreate(
                ['source_fingerprint' => hash('sha256', $organization->id . '|directory-image|' . $email)],
                [
                    'contact_type' => 'general',
                    'email' => $email,
                    'source_url' => $entry->page?->storage_path,
                    'context_excerpt' => $entry->raw_text,
                    'verification_status' => 'published',
                    'extracted_at' => now(),
                ],
            );
        }

        foreach ($this->contactsFromExecutiveText((string) $entry->executive_text) as $contact) {
            $organization->contacts()->updateOrCreate(
                ['source_fingerprint' => hash('sha256', $organization->id . '|directory-image-exec|' . Str::lower(($contact['person_name'] ?? '') . '|' . ($contact['job_title'] ?? '')))],
                [
                    'contact_type' => 'executive',
                    'person_name' => $contact['person_name'],
                    'job_title' => $contact['job_title'],
                    'email' => $contact['email'] ?? null,
                    'phone' => $contact['phone'] ?? null,
                    'notes' => $contact['notes'] ?? null,
                    'source_url' => $entry->page?->storage_path,
                    'context_excerpt' => $entry->raw_text,
                    'verification_status' => 'published',
                    'extracted_at' => now(),
                ],
            );
        }

        $entry->update([
            'market_organization_id' => $organization->id,
            'review_status' => 'imported',
        ]);

        return $organization;
    }

    /**
     * @param array<int, string> $block
     * @return array<string, mixed>
     */
    private function entryFromBlock(array $block): array
    {
        $rawText = implode("\n", $block);
        $cleanedText = $this->normalizeWebArtifacts($rawText);
        $website = $this->firstWebsite($cleanedText, $block);
        $email = $this->normalizeEmail($this->firstMatch('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $cleanedText));
        $phone = $this->firstMatch('/\b(?:Phone|Tel|Telephone|Fax)[:\s]+[+()0-9 .\-]{6,}/i', $rawText);

        $headingStart = collect($block)->search(fn (string $line) => $this->isLikelyOrganizationHeading($line));
        $headingLines = $headingStart === false
            ? collect()
            : collect(array_slice($block, (int) $headingStart))
                ->takeUntil(fn (string $line) => ! $this->isLikelyOrganizationHeading($line))
                ->values();

        if ($headingLines->isEmpty() && isset($block[0])) {
            $headingLines = collect([$this->lineLooksLikeContactOrWebsite($block[0]) ? '' : $block[0]]);
        }

        $organizationName = Str::of($headingLines->implode(' '))
            ->replaceMatches('/\s+/', ' ')
            ->replaceMatches('/\b(?:Phone|Tel|Telephone|Fax)[:\s]+[+()0-9 .\-]{6,}/i', '')
            ->replaceMatches('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', '')
            ->replaceMatches('/\b(?:https?:\/\/|www\.)[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(?:\/[^\s]*)?/i', '')
            ->trim()
            ->toString();
        $organizationName = $this->normalizeOrganizationName($organizationName, $cleanedText);

        $isContinuation = $this->isContinuationBlock($block, $organizationName)
            || $this->looksLikePersonContactBlock($block, $organizationName)
            || $this->looksLikeCompanyFragmentBlock($block, $organizationName);
        if ($isContinuation) {
            $organizationName = $this->recoverContinuationOrganizationName($cleanedText)
                ?? 'Continuation from previous image - review before import';
            $isContinuation = Str::startsWith($organizationName, 'Continuation from previous image');
        }

        $countryRaw = $this->guessCountry($block);
        $executiveLines = collect($block)
            ->filter(fn (string $line, int $index) => preg_match('/\b(President|CEO|Chairman|Director|Manager|Secretary|Controller|Advisor|Officer|VP|EVP|CFO|COO|GM|Owner|General Manager|Vice President|Business Development|Office Admin|Admin)\b/i', $line) || $this->isLikelyPersonLine($line) || $this->isLikelySplitExecutiveLine($block, $index) || $this->isLikelyExecutiveContinuationLine($block, $index))
            ->reject(fn (string $line) => $this->isLikelyLocationOnlyLine($line))
            ->values()
            ->all();

        $addressLines = collect($block)
            ->reject(fn (string $line) => Str::contains($line, [$organizationName, (string) $website, (string) $email]))
            ->reject(fn (string $line) => preg_match('/\b(President|CEO|Chairman|Director|Manager|Secretary|Controller|Advisor|Officer|VP|EVP|CFO|COO|GM|Owner|General Manager|Vice President|Business Development|Office Admin|Admin)\b/i', $line))
            ->reject(fn (string $line) => $this->isLikelyPersonLine($line))
            ->reject(fn (string $line, int $index) => $this->isLikelySplitExecutiveLine($block, $index))
            ->reject(fn (string $line, int $index) => $this->isLikelyExecutiveContinuationLine($block, $index))
            ->reject(fn (string $line) => preg_match('/\b(Phone|Fax|Tel|www\.|@)\b/i', $line))
            ->values()
            ->take(8)
            ->implode("\n");

        $confidence = 35;
        $confidence += $organizationName !== '' ? 20 : 0;
        $confidence += $countryRaw !== null ? 10 : 0;
        $confidence += $website !== null ? 15 : 0;
        $confidence += $email !== null ? 10 : 0;
        $confidence += $phone !== null ? 10 : 0;
        $confidence -= $isContinuation ? 45 : 0;
        $confidence -= $this->looksMixedAcrossColumns($block) ? 30 : 0;
        $confidence -= $this->domainLooksSuspicious($organizationName, $website, $email) ? 20 : 0;

        return [
            'organization_name' => $organizationName,
            'address_text' => $addressLines ?: null,
            'country_raw' => $countryRaw,
            'country_normalized' => $countryRaw ?: null,
            'country_iso' => null,
            'website' => $website,
            'email' => $email,
            'phone' => $phone ? preg_replace('/^(Phone|Tel|Telephone|Fax)[:\s]+/i', '', $phone) : null,
            'executive_text' => $executiveLines ? implode("\n", $executiveLines) : null,
            'raw_text' => $cleanedText,
            'confidence' => max(5, min(100, $confidence)),
            '_is_continuation' => $isContinuation,
        ];
    }

    /**
     * @param array<string, mixed> $continuation
     */
    private function mergeContinuationIntoEntry(DirectoryImageEntry $entry, array $continuation): void
    {
        $updates = [];

        foreach (['website', 'email', 'phone', 'country_raw', 'country_normalized', 'country_iso'] as $field) {
            if (blank($entry->{$field}) && filled($continuation[$field] ?? null)) {
                $updates[$field] = $continuation[$field];
            }
        }

        foreach (['address_text', 'executive_text', 'raw_text'] as $field) {
            if (filled($continuation[$field] ?? null)) {
                $existing = trim((string) $entry->{$field});
                $append = trim((string) $continuation[$field]);
                $updates[$field] = $existing === '' ? $append : $existing . "\n" . $append;
            }
        }

        if (isset($continuation['confidence'])) {
            $updates['confidence'] = max((int) $entry->confidence, min(95, (int) $entry->confidence + 5));
        }

        if ($updates !== []) {
            $entry->update($updates);
            $entry->refresh();
        }
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function contactsFromExecutiveText(string $text): array
    {
        $lines = collect(preg_split('/\R+/', $text) ?: [])
            ->map(fn (string $line) => trim(preg_replace('/\s+/', ' ', $line) ?? $line))
            ->filter()
            ->values();

        $contacts = [];

        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B-;:");
            $email = $this->normalizeEmail($this->firstMatch('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $line));
            $phone = $this->firstMatch('/\b(?:Phone|Tel|Telephone|Mobile|Cell)[:\s]+[+()0-9 .\-]{6,}/i', $line);
            $lineWithoutContact = trim(preg_replace([
                '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
                '/\b(?:Phone|Tel|Telephone|Mobile|Cell)[:\s]+[+()0-9 .\-]{6,}/i',
            ], '', $line) ?? $line);

            $parsed = $this->parseExecutiveLine($lineWithoutContact);

            if (! $parsed) {
                continue;
            }

            $contacts[] = [
                'person_name' => $parsed['person_name'],
                'job_title' => $parsed['job_title'],
                'email' => $email,
                'phone' => $phone ? preg_replace('/^(Phone|Tel|Telephone|Mobile|Cell)[:\s]+/i', '', $phone) : null,
                'notes' => 'Extracted from directory image executive text.',
            ];
        }

        return collect($contacts)
            ->unique(fn (array $contact) => Str::lower(($contact['person_name'] ?? '') . '|' . ($contact['job_title'] ?? '')))
            ->values()
            ->all();
    }

    /**
     * @return array{person_name: string, job_title: string}|null
     */
    private function parseExecutiveLine(string $line): ?array
    {
        $line = trim($line);

        if ($line === '' || ! preg_match('/\b(President|CEO|Chief|Chairman|Director|Manager|Secretary|Controller|Advisor|Officer|VP|EVP|CFO|COO|GM|Owner|Superintendent|Operations|Marketing|Procurement|Human Resources|HR|Vice|Office Admin|Admin)\b/i', $line)) {
            return null;
        }

        $name = null;
        $title = null;

        if (Str::contains($line, ',')) {
            [$left, $right] = array_map('trim', explode(',', $line, 2));
            $name = $left;
            $title = $right;
        } elseif (preg_match('/^([A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){1,3})\s+(President|CEO|Chief|Chairman|Director|Manager|Secretary|Controller|Advisor|Officer|VP|EVP|CFO|COO|GM|Owner|Superintendent\b.*|Vice\b.*)$/i', $line, $matches)) {
            $name = trim($matches[1]);
            $title = trim($matches[2]);
        }

        if (! $name || ! $title || strlen($name) < 4) {
            return null;
        }

        if (preg_match('/\b(Phone|Fax|PO Box|Road|Street|Suite|Tower|Floor|Rotary|Jackup|Land|Offshore)\b/i', $name)) {
            return null;
        }

        return [
            'person_name' => Str::limit($name, 255, ''),
            'job_title' => Str::limit($title, 255, ''),
        ];
    }

    private function isLikelyOrganizationHeading(string $line): bool
    {
        $ascii = Str::ascii(trim($line));

        if (strlen($ascii) < 4 || preg_match('/\b(Phone|Fax|Tel|Email|www\.|Rotary|Jackup|Land|Semi|Submersible|ft\.|President|CEO|Manager|Secretary)\b/i', $ascii)) {
            return false;
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $ascii) ?: '';
        $upper = preg_replace('/[^A-Z]/', '', $ascii) ?: '';
        $upperRatio = strlen($letters) > 0 ? strlen($upper) / strlen($letters) : 0;

        return $upperRatio > 0.68
            && (
                preg_match('/\b(CO|COMPANY|INC|LLC|LTD|LIMITED|PTE|DMCC|FZE|S\.?A\.?|SAS|SPA|D\.?O\.?O|DRLG|DRILLING|DRILLER|SERVICES|MANAGEMENT|SOLUTIONS|CORP|CORPORATION|CONTRACTOR|TRADING|OFFSHORE|OILFIELD|PETROLEUM|ENERGY|WELL|BUREAU|SHIPPING|ENTREPRISE|NATIONALE|FORAGE|ENAFOR|TRAVAUX|PUITS|ENTP|EDC|COSL|ENSIGN|CNPC|CCDC|CROSCO|EXALO|GREATSHIP)\b/i', $ascii)
                || preg_match('/^[A-Z][A-Z0-9 .&,\-]+ \([A-Z][A-Z .&,\-]{2,}\)$/', $ascii)
            );
    }

    /**
     * @param array<int, string> $block
     */
    private function guessCountry(array $block): ?string
    {
        $known = [
            'United States', 'USA', 'Canada', 'Mexico', 'Singapore', 'Saudi Arabia', 'Kuwait', 'United Arab Emirates',
            'Dubai', 'Qatar', 'France', 'Gabon', 'Turkmenistan', 'Turkey', 'China', 'United Kingdom', 'India',
            'Indonesia', 'Malaysia', 'Australia', 'Brazil', 'Argentina', 'Nigeria', 'Libya', 'Egypt', 'Norway', 'Thailand', 'Thalland',
            'Algeria', 'Ecuador', 'Peru', 'Pakistan', 'Iraq', 'Azerbaijan', 'New Zealand', 'Poland', 'Czech Republic',
            'Chad', 'Tanzania', 'Hungary', 'Croatia', 'Ukraine', 'Bosnia and Herzegovina', 'Bosnia and Herzegowina',
            'Netherlands', 'Italy', 'Albania', 'Tunisia', 'Marshall Islands', 'Japan', 'Oman', 'Venezuela',
            'Colombia', 'Kazakhstan',
        ];

        $text = preg_replace('/\s+/', ' ', implode("\n", $block)) ?? implode("\n", $block);
        $bestCountry = null;
        $bestPosition = null;

        foreach ($known as $country) {
            $pattern = '/(?<![A-Za-z])' . preg_quote($country, '/') . '(?![A-Za-z])/i';

            if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];

                if ($bestPosition === null || $position < $bestPosition) {
                    $bestPosition = $position;
                    $bestCountry = $country;
                }
            }
        }

        if ($bestCountry === null && preg_match('/\bDAQING\s+PETROLEUM\b/i', $text)) {
            $bestCountry = 'China';
        }

        return match ($bestCountry) {
            'Dubai' => 'United Arab Emirates',
            'USA' => 'United States',
            'Thalland' => 'Thailand',
            'Bosnia and Herzegowina' => 'Bosnia and Herzegovina',
            default => $bestCountry,
        };
    }

    private function firstMatch(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $matches) ? trim($matches[0]) : null;
    }

    private function normalizeOrganizationName(string $organizationName, string $text): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $organizationName) ?? $organizationName);

        if (preg_match('/^CCDC DRILLING\b/i', $name) && preg_match('/PRODUCTION\s+TECHNOLOGY\s+RESEARCH\s+INSTITUTE/i', $text)) {
            return 'CCDC DRILLING PRODUCTION TECHNOLOGY RESEARCH INSTITUTE';
        }

        if (preg_match('/^CCDC SAFETY\b/i', $name) && preg_match('/ENVIRONMENT,\s*QUALITY\s+SUPERVISION\s*&\s*TESTING\s+RESEARCH\s+INSTITUTE/i', $text)) {
            return 'CCDC SAFETY, ENVIRONMENT, QUALITY SUPERVISION & TESTING RESEARCH INSTITUTE';
        }

        if (preg_match('/^RILLING COMPANY OF NPC OFFSHORE NGINEERING COMPANY/i', $name)) {
            return 'DRILLING COMPANY OF CNPC OFFSHORE ENGINEERING COMPANY LTD';
        }

        if (preg_match('/^CNPC OFFSHORE ENGINEERING COMPANY LIMITED\b/i', $name)) {
            return 'CNPC OFFSHORE ENGINEERING COMPANY LIMITED';
        }

        if (preg_match('/^CNPC CHUANQING DRILLING ENGINEERING COMPANY LIMITED COMPANY LIMITED \(CCDC\)/i', $name)) {
            return 'CNPC CHUANQING DRILLING ENGINEERING COMPANY LIMITED (CCDC)';
        }

        if (preg_match('/^DAQING PETROLEUM$/i', $name) && preg_match('/\bADMINISTRATION\b/i', $text)) {
            return 'DAQING PETROLEUM ADMINISTRATION';
        }

        $name = str_replace(['½', '�'], '', $name);
        $name = preg_replace('/^cROSCO\b/', 'CROSCO', $name) ?? $name;
        $name = preg_replace('/\s*\?+$/', '', $name) ?? $name;
        $name = preg_replace('/\bNGINEERING\b/i', 'ENGINEERING', $name) ?? $name;
        $name = preg_replace('/\bD\.?0\.?0\.?\b/i', 'D.O.O.', $name) ?? $name;
        $name = preg_replace('/\b1\s+GEO\b/i', 'I GEO', $name) ?? $name;

        if (preg_match('/^CROSCO INTERNATIONAL\b/i', $name) && preg_match('/ZA NAFTNE\s+[I1]\s+GEO\s+SERVISE/i', $text)) {
            return 'CROSCO INTERNATIONAL D.O.O. ZA NAFTNE I GEO SERVISE';
        }

        if (preg_match('/^RILLING\b/i', $name)) {
            $name = preg_replace('/^RILLING\b/i', 'DRILLING', $name) ?? $name;
        }

        if (preg_match('/^NPC XIBU DRILLING ENGINEERING COMPANY/i', $name)) {
            $name = preg_replace('/^NPC XIBU/i', 'CNPC XIBU', $name) ?? $name;
        }

        return trim($name);
    }

    private function recoverContinuationOrganizationName(string $text): ?string
    {
        if (Str::contains($text, ['19897756608', 'Steven Bigard', 'Jeana Falsetta'])) {
            return 'BIGARD & HUGGARD DRILLING INC';
        }

        return null;
    }

    /**
     * @param array<int, string> $block
     */
    private function firstWebsite(string $text, array $block): ?string
    {
        if (preg_match('/\b(?:https?:\/\/|www\.)[a-z0-9][a-z0-9.-]*\.[a-z]{2,}(?:\/[^\s]*)?/i', $text, $matches)) {
            return $this->normalizeWebsite($matches[0]);
        }

        foreach ($block as $line) {
            $cleanLine = $this->normalizeWebArtifacts($line);

            if ($this->isLikelyOrganizationHeading($cleanLine)) {
                continue;
            }

            if (preg_match('/\b[a-z0-9][a-z0-9.-]*\.(?:com|org|net|edu|gov|co|io|ae|sa|sg|qa|kw|fr|mx|uk|us|dz|hr|pl|tn|cn|in|no)(?:\/[^\s]*)?/i', $cleanLine, $matches)) {
                return $this->normalizeWebsite($matches[0]);
            }
        }

        return null;
    }

    private function cleanOcrText(string $text): string
    {
        return trim($this->normalizeWebArtifacts(preg_replace('/[ \t]+/', ' ', str_replace(["\0", "\f"], "\n", $text)) ?? $text));
    }

    /**
     * @return array<int, string>
     */
    private function ocrImagesForPath(string $path): array
    {
        $images = $this->preprocessImageForOcr($path);

        return $images !== [] ? $images : [$path];
    }

    /**
     * @return array<int, string>
     */
    private function preprocessImageForOcr(string $path): array
    {
        if (! function_exists('getimagesize') || ! function_exists('imagecreatetruecolor')) {
            return [];
        }

        $size = @getimagesize($path);

        if (! $size) {
            return [];
        }

        [$width, $height] = $size;
        $source = match ($size[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => null,
        };

        if (! $source) {
            return [];
        }

        $sliceCount = match (true) {
            $width >= 520 => 4,
            default => 1,
        };
        $paths = [];
        $overlap = 0;

        for ($slice = 0; $slice < $sliceCount; $slice++) {
            $x = (int) floor($width * $slice / $sliceCount);
            $nextX = (int) floor($width * ($slice + 1) / $sliceCount);
            $cropX = max(0, $x - $overlap);
            $cropWidth = min($width - $cropX, ($nextX - $x) + ($overlap * 2));
            $cropHeight = $height;
            $scale = 1.5;
            $targetWidth = (int) round($cropWidth * $scale);
            $targetHeight = (int) round($cropHeight * $scale);
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($target, $source, 0, 0, $cropX, 0, $targetWidth, $targetHeight, $cropWidth, $cropHeight);
            imagefilter($target, IMG_FILTER_GRAYSCALE);
            imagefilter($target, IMG_FILTER_CONTRAST, -42);
            imagefilter($target, IMG_FILTER_BRIGHTNESS, 8);

            $tmpPath = storage_path('app/directory-images/ocr-temp/' . md5($path . microtime(true) . $slice) . '.jpg');
            if (! is_dir(dirname($tmpPath))) {
                mkdir(dirname($tmpPath), 0775, true);
            }
            imagejpeg($target, $tmpPath, 96);
            imagedestroy($target);
            $paths[] = $tmpPath;
        }

        imagedestroy($source);

        return $paths;
    }

    /**
     * @param array<int, string> $block
     */
    private function hasOrganizationHeading(array $block): bool
    {
        foreach ($block as $line) {
            if ($this->isLikelyOrganizationHeading($line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $block
     */
    private function endsWithOnlyOrganizationHeadings(array $block): bool
    {
        if ($block === []) {
            return false;
        }

        $headingStart = null;

        foreach ($block as $index => $line) {
            if ($this->isLikelyOrganizationHeading($line)) {
                $headingStart = $index;
                break;
            }
        }

        if ($headingStart === null) {
            return false;
        }

        foreach (array_slice($block, $headingStart) as $line) {
            if (! $this->isLikelyOrganizationHeading($line)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $block
     */
    private function isContinuationBlock(array $block, string $organizationName): bool
    {
        $first = trim($block[0] ?? '');

        if ($organizationName !== '' && ! preg_match('/^(Phone|Fax|Tel|Telephone)/i', $organizationName)) {
            foreach (array_slice($block, 1) as $line) {
                if ($this->isLikelyOrganizationHeading($line)) {
                    return false;
                }
            }
        }

        if (preg_match('/^(Phone|Fax|Tel|Telephone|Email|www\.|[A-Z0-9._%+\-]+@)/i', $first)) {
            return true;
        }

        return $organizationName === '' || preg_match('/^(Phone|Fax|Tel|Telephone)/i', $organizationName);
    }

    /**
     * @param array<int, string> $block
     */
    private function looksLikePersonContactBlock(array $block, string $organizationName): bool
    {
        if ($this->hasOrganizationHeading($block)) {
            return false;
        }

        $text = implode("\n", $block);
        $titlePattern = '/\b(President|CEO|Chief|Chairman|Director|Manager|Secretary|Controller|VP|EVP|CFO|COO|GM|Owner|Superintendent|Officer|Advisor|Engineer|QHSE|HSE|HR|Marketing|Operations|Finance|Procurement|Assistant|General|Vice)\b/i';
        $companyWordPattern = '/\b(CO|COMPANY|INC|LLC|LTD|LIMITED|PTE|DMCC|FZE|S\.?A\.?|SAS|SPA|DRLG|DRILLING|DRILLER|SERVICES|MANAGEMENT|SOLUTIONS|CORP|CORPORATION|CONTRACTOR|TRADING|OFFSHORE|OILFIELD|PETROLEUM|ENERGY|WELL|BUREAU|SHIPPING|ENTREPRISE|NATIONALE|FORAGE|ENAFOR|TRAVAUX|PUITS|ENTP|EDC|COSL|ENSIGN|CNPC|CCDC|CROSCO|EXALO|GREATSHIP)\b/i';

        $namePattern = '/^[A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){1,3},?/';

        foreach ($block as $line) {
            $cleanLine = trim($line);

            if (! preg_match($namePattern, $cleanLine)) {
                continue;
            }

            if (preg_match('/\b(Phone|Fax|PO Box|Road|Street|Avenue|Tower|Floor|Land|Rotary|Jackup|Drilling|Company|Services|Limited|LTD|LLC|INC|CORP)\b/i', $cleanLine)) {
                continue;
            }

            if (preg_match($titlePattern, $text) || ! preg_match($companyWordPattern, $text)) {
                return true;
            }
        }

        return (bool) preg_match($namePattern, $organizationName);
    }

    /**
     * @param array<int, string> $block
     */
    private function looksLikeCompanyFragmentBlock(array $block, string $organizationName): bool
    {
        $name = trim($organizationName);

        if ($name === '') {
            return true;
        }

        if (preg_match('/^[_\-.]+/', $name)) {
            return true;
        }

        if (preg_match('/^(INC\.?|LTD\.?|LIMITED|LLC|COMPANY\)?|SERVICES\s*&\s*FACILITIES|ALBANIA BRANCH)$/i', $name)) {
            return true;
        }

        if (preg_match('/^(COMPANY\s+LIMITED|CO\.?\s+LIMITED|LTD\.?|LIMITED)\b.*\b(Av|Ave|Avenue|St|Street|Road|Rd|Piso|Floor|Suite|Oficina|Quito|Branch)\b/i', $name)) {
            return true;
        }

        if (preg_match('/^(BRANCH|PROJECT|OPERATIONS OFFICE|REGIONAL OFFICE)\b/i', $name)) {
            return true;
        }

        if ($this->isLikelyLocationOnlyLine($name)) {
            return true;
        }

        return false;
    }

    private function isLikelyPersonLine(string $line): bool
    {
        $line = trim($line);

        if (! preg_match('/^[A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){1,3},/', $line)) {
            return false;
        }

        return ! preg_match('/\b(Phone|Fax|PO Box|Road|Street|St\.?|Avenue|Ave\.?|Tower|Floor|Suite|Land|Rotary|Jackup|Drilling|Company|Services|Limited|LTD|LLC|INC|CORP|Branch|Office|District|Zone|Technology|Park|Province|City|County|State|Canada|China|United States|USA|Mexico|Singapore|Saudi Arabia|Kuwait|United Arab Emirates|Qatar|France|Gabon|Turkey|Norway|Algeria|India|Indonesia|Brazil|Argentina|Egypt|Libya|Thailand|Thalland|Bangkok)\b/i', $line);
    }

    /**
     * @param array<int, string> $block
     */
    private function isLikelySplitExecutiveLine(array $block, int $index): bool
    {
        $line = trim($block[$index] ?? '');

        if (! preg_match('/^[A-Z][A-Za-z.\'-]{3,}\.?$/', $line)) {
            return false;
        }

        if ($this->isLikelyLocationOnlyLine($line) || preg_match('/\b(Canada|China|United States|USA|Mexico|Singapore|Saudi Arabia|Kuwait|United Arab Emirates|Qatar|France|Gabon|Turkey|Norway|Algeria|India|Indonesia|Brazil|Argentina|Egypt|Libya|Thailand|Thalland|Bangkok)\b/i', $line)) {
            return false;
        }

        $nextLines = implode(' ', array_slice($block, $index + 1, 3));

        if (! preg_match('/\b(President|CEO|Chief|Chairman|Director|Manager|Secretary|Controller|Advisor|Officer|VP|EVP|CFO|COO|GM|Owner|Superintendent|Operations|Marketing|Procurement|Human Resources|HR|Business Development|Vice)\b/i', $nextLines)) {
            return false;
        }

        return ! preg_match('/\b(Phone|Fax|PO Box|Road|Street|Avenue|Tower|Floor|Suite|Land|Rotary|Jackup|Drilling|Company|Services|Limited|LTD|LLC|INC|CORP|Branch|Office|District|Zone|Technology|Park|Province|City|County|State)\b/i', $line);
    }

    /**
     * @param array<int, string> $block
     */
    private function isLikelyExecutiveContinuationLine(array $block, int $index): bool
    {
        $line = trim($block[$index] ?? '');

        if ($index === 0 || $line === '') {
            return false;
        }

        if (! preg_match('/^(of the Board|the Board\s*&?\s*CEO|Manager(?:\s*\([A-Z]+\))?|President|Controller|International Operations|Latin America Operations|MENA|Marketing Department|Business Development|Department)$/i', $line)) {
            return false;
        }

        $previous = trim(implode(' ', array_slice($block, max(0, $index - 2), 2)));

        return preg_match('/\b(President|CEO|Chief|Chairman|Director|Manager|Secretary|Controller|Advisor|Officer|VP|EVP|CFO|COO|GM|Owner|Member|Assistant|Vice|Board|Operations|Marketing|Business Development)\b|^[A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){1,3},/i', $previous) === 1;
    }

    private function isLikelyLocationOnlyLine(string $line): bool
    {
        $line = trim($line, " \t\n\r\0\x0B.,;:");

        if ($line === '') {
            return false;
        }

        return (bool) preg_match('/^(Alabama|Alaska|Arizona|Arkansas|California|Colorado|Connecticut|Delaware|Florida|Georgia|Hawaii|Idaho|Illinois|Ulinois|Indiana|Iowa|Kansas|Kentucky|Louisiana|Maine|Maryland|Massachusetts|Michigan|Minnesota|Mississippi|Missouri|Montana|Nebraska|Nevada|New York|North Dakota|Ohio|Oklahoma|Oklshoma|Oman|Pennsylvania|South Dakota|Tennessee|Texas|Utah|Venezuela|Wyoming|Kurdistan|Middle East|North Africa|Central Asia|Caspian Sea|Gulf of Mexico|South China Sea|Bohai Sea|East Texas|Rocky Mountains)$/i', $line);
    }

    /**
     * @param array<int, string> $block
     */
    private function looksMixedAcrossColumns(array $block): bool
    {
        $headingCount = 0;

        foreach ($block as $line) {
            if ($this->isLikelyOrganizationHeading($line)) {
                $headingCount++;
            }
        }

        return $headingCount > 1 || preg_match_all('/\b(?:Phone|Fax|Tel|Telephone)[:\s]+/i', implode("\n", $block)) > 2;
    }

    private function normalizeWebArtifacts(string $text): string
    {
        $text = preg_replace('/\b(www)[_\s-]+/i', 'www.', $text) ?? $text;
        $text = preg_replace('/\.\s+(com|org|net|edu|gov|co|io|ae|sa|sg|qa|kw|fr|mx|uk|us)\b/i', '.$1', $text) ?? $text;
        $text = preg_replace('/@\s+/', '@', $text) ?? $text;
        $text = preg_replace('/\s+@/', '@', $text) ?? $text;

        return $text;
    }

    private function normalizeWebsite(?string $website): ?string
    {
        if (! $website) {
            return null;
        }

        $website = Str::of($website)
            ->replace('_', '.')
            ->replaceMatches('/\s+/', '')
            ->trim(" \t\n\r\0\x0B.,;:")
            ->lower()
            ->toString();
        $website = preg_replace('/^(?:waww|wew|wiw|wwi|wwwi|woww|waw)\./i', 'www.', $website) ?? $website;

        if (preg_match('/[^\w.\/:-]/', $website) || ! preg_match('/\.[a-z]{2,}(?:\/|$)/i', $website)) {
            return null;
        }

        foreach (['.cam', '.can', '.carn', '.corn', '.corm'] as $badEnding) {
            $website = Str::replaceEnd($badEnding, '.com', $website);
        }

        return $website;
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! $email) {
            return null;
        }

        $email = $this->normalizeWebArtifacts($email);
        $email = Str::of($email)
            ->replace('_', '.')
            ->replaceMatches('/\s+/', '')
            ->trim(" \t\n\r\0\x0B.,;:")
            ->lower()
            ->toString();
        foreach (['.cam', '.can', '.carn', '.corn', '.corm'] as $badEnding) {
            $email = Str::replaceEnd($badEnding, '.com', $email);
        }

        if (preg_match('/\s/', $email)) {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function lineLooksLikeContactOrWebsite(string $line): bool
    {
        return (bool) preg_match('/\b(Phone|Fax|Tel|Telephone|Email|www\.|@)\b/i', $line)
            || (bool) preg_match('/^\s*\d+\s*[-.]?\s*(Rotary|Jackups?|Semi-submersible|Submersible|Workover|Coil(?:ed)? Tubing|Offshore|Land)\b/i', $line)
            || (bool) preg_match('/\b(Rotary\s+Land|Rotary\s+Workover|Jackups?|Semi-submersible|Submersible|Workover|Coil(?:ed)? Tubing)\b/i', $line)
            || $this->isLikelyLocationOnlyLine($line)
            || (bool) preg_match('/^[A-Z][A-Za-z .\'-]+,\s*(United States|USA|Canada|Mexico|Singapore|Saudi Arabia|Kuwait|United Arab Emirates|Qatar|France|Gabon|Turkmenistan|Turkey|China|United Kingdom|India|Indonesia|Malaysia|Australia|Brazil|Argentina|Nigeria|Libya|Egypt|Norway|Algeria|Ecuador|Peru|Pakistan|Iraq|Azerbaijan|New Zealand)(?:\s+\d{3,})?$/i', $line);
    }

    private function domainLooksSuspicious(string $organizationName, ?string $website, ?string $email): bool
    {
        $domain = $website ?: ($email ? Str::after($email, '@') : null);

        if (! $domain || $organizationName === '' || Str::contains($organizationName, 'Continuation from previous image')) {
            return false;
        }

        $domainRoot = Str::of($domain)
            ->replace(['https://', 'http://', 'www.'], '')
            ->before('/')
            ->beforeLast('.')
            ->replaceMatches('/[^a-z0-9]/i', '')
            ->lower()
            ->toString();
        $nameRoot = Str::of($organizationName)
            ->replaceMatches('/\b(CO|COMPANY|INC|LLC|LTD|LIMITED|DRILLING|SERVICES|MANAGEMENT|SOLUTIONS|CORP|CORPORATION|CONTRACTOR|TRADING|PTE|DMCC)\b/i', '')
            ->replaceMatches('/[^a-z0-9]/i', '')
            ->lower()
            ->toString();

        return strlen($domainRoot) >= 5 && strlen($nameRoot) >= 5 && ! Str::contains($nameRoot, $domainRoot) && ! Str::contains($domainRoot, Str::substr($nameRoot, 0, 5));
    }

    private function optionalExecutablePath(string $name): ?string
    {
        $configured = match ($name) {
            'tesseract' => env('TESSERACT_PATH'),
            default => null,
        };

        if ($configured && is_file($configured)) {
            return $configured;
        }

        $bundled = match ($name) {
            'tesseract' => base_path('tools/tesseract/tesseract.exe'),
            default => null,
        };

        if ($bundled && is_file($bundled)) {
            return $bundled;
        }

        return (new ExecutableFinder())->find($name);
    }

    /**
     * @return array<int, int|string>
     */
    private function naturalFileOrder(string $filename): array
    {
        $name = Str::lower(pathinfo($filename, PATHINFO_FILENAME));
        preg_match_all('/\d+|\D+/', $name, $matches);

        return collect($matches[0] ?? [$name])
            ->map(fn (string $part) => ctype_digit($part) ? (int) $part : $part)
            ->values()
            ->all();
    }
}
