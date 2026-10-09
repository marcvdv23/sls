<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryTopic;
use App\Models\CountryUpdate;
use App\Support\CountryUpdateDedupeRules;
use App\Support\ReviewFocuses;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class TendersOnTimeImportService
{
    public function import(
        string $date,
        bool $dryRun = false,
        ?string $endpoint = null,
        ?string $username = null,
        ?string $key = null,
        int $timeoutSeconds = 45,
        int $inspectRawLimit = 0,
        bool $checkDocuments = false,
    ): array {
        $startedAt = now();
        $postingDate = Carbon::parse($date)->toDateString();
        $endpoint ??= env('TENDERSONTIME_ENDPOINT', 'https://tmproject.tendersontime.org/tmpApi/tender-pull-json-2interact.php');
        $username ??= env('TENDERSONTIME_USERNAME');
        $key ??= env('TENDERSONTIME_KEY');

        if (blank($username) || blank($key)) {
            throw new RuntimeException('TendersOnTime credentials are not configured. Set TENDERSONTIME_USERNAME and TENDERSONTIME_KEY.');
        }

        $response = Http::timeout(max(10, $timeoutSeconds))
            ->acceptJson()
            ->get($endpoint, [
                'username' => $username,
                'key' => $key,
                'date' => $postingDate,
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('TendersOnTime API returned HTTP ' . $response->status() . ': ' . Str::limit((string) $response->body(), 500));
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('TendersOnTime API did not return a JSON object.');
        }

        if (($payload['status'] ?? null) !== 'success') {
            throw new RuntimeException('TendersOnTime API error: ' . (string) ($payload['message'] ?? 'Unknown error'));
        }

        $parsedItems = collect($payload['data'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $this->normalizeTender($item, $postingDate))
            ->filter(fn (array $item) => filled($item['source_url']) && filled($item['title']))
            ->values();
        $items = $parsedItems
            ->map(fn (array $item) => $this->withMatchedKeywords($item))
            ->filter(fn (array $item) => $item['matched_keywords'] !== [])
            ->values();
        $filteredOut = $parsedItems->count() - $items->count();
        $rawInspectionItems = $inspectRawLimit > 0
            ? $parsedItems
                ->take($inspectRawLimit)
                ->map(fn (array $item) => $this->inspectTender($this->withMatchedKeywords($item), $checkDocuments, $timeoutSeconds))
                ->values()
                ->all()
            : [];

        $stored = collect();
        $errors = [];

        if (! $dryRun) {
            $stored = $items->map(function (array $item) use (&$errors) {
                try {
                    return $this->storeTender($item);
                } catch (\Throwable $exception) {
                    $errors[] = $item['external_id'] . ': ' . $exception->getMessage();

                    return null;
                }
            })->filter()->values();

            $this->storeMonitorRuns($stored, $items, $startedAt, $endpoint, $errors);
        }

        return [
            'posting_date' => $postingDate,
            'endpoint' => $endpoint,
            'total_found' => (int) ($payload['total_found'] ?? $payload['total'] ?? $items->count()),
            'total_shown' => (int) ($payload['total_shown'] ?? $items->count()),
            'items_parsed' => $parsedItems->count(),
            'items_found' => $items->count(),
            'items_filtered_out' => $filteredOut,
            'stored_count' => $stored->count(),
            'errors' => $errors,
            'items' => $items->all(),
            'raw_inspection_items' => $rawInspectionItems,
        ];
    }

    private function normalizeTender(array $item, string $postingDate): array
    {
        $externalId = $this->cleanText($item['tot_id'] ?? $item['id'] ?? $item['tender_id'] ?? '');
        $title = $this->cleanText($item['title'] ?? $item['tender_title'] ?? '');
        $description = $this->cleanText($item['description'] ?? $item['tender_description'] ?? '');
        $noticeDocument = trim((string) ($item['notice_document'] ?? $item['document_url'] ?? $item['url'] ?? ''));
        $additionalDocuments = collect($item['additional_documents'] ?? [])
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->values()
            ->all();
        $sourceUrl = $noticeDocument ?: ($additionalDocuments[0] ?? '');
        $countryIso = strtoupper($this->cleanText($item['country_iso'] ?? ''));
        $countryName = $this->cleanText($item['purchaser_country'] ?? $item['country'] ?? '');
        $closingDate = $this->dateOrNull($item['closing_date'] ?? null);
        $postingDate = $this->dateOrNull($item['posting_date'] ?? null) ?: $postingDate;
        $value = $this->cleanText($item['tender_value'] ?? '');
        $currency = strtoupper($this->cleanText($item['currency'] ?? ''));
        $purchaserName = $this->cleanText($item['purchaser_name'] ?? '');
        $purchaserAddress = $this->cleanText($item['purchaser_address'] ?? '');
        $purchaserEmail = $this->cleanText($item['purchaser_email'] ?? '');
        $purchaserWebsite = $this->cleanText($item['purchaser_website'] ?? '');
        $noticeNumber = $this->cleanText($item['tender_notice_no'] ?? '');
        $documentType = $this->cleanText($item['document_type'] ?? '');
        $biddingType = $this->cleanText($item['bidding_type'] ?? '');
        $financier = $this->cleanText($item['financier'] ?? '');
        $cpv = $this->cleanText($item['cpv'] ?? '');

        $summaryLines = array_values(array_filter([
            '[Official tender source] TendersOnTime tender notice.',
            $description,
            $noticeNumber ? 'Notice number: ' . $noticeNumber . '.' : null,
            $documentType ? 'Document type: ' . $documentType . '.' : null,
            $biddingType ? 'Bidding type: ' . $biddingType . '.' : null,
            $closingDate ? 'Closing date: ' . $closingDate . '.' : null,
            $purchaserName ? 'Purchaser: ' . $purchaserName . '.' : null,
            $purchaserEmail ? 'Purchaser email: ' . $purchaserEmail . '.' : null,
            $purchaserWebsite ? 'Purchaser website: ' . $purchaserWebsite . '.' : null,
            $value !== '' ? 'Tender value: ' . trim($value . ' ' . $currency) . '.' : null,
            $financier ? 'Financier: ' . $financier . '.' : null,
            $cpv ? 'CPV: ' . $cpv . '.' : null,
            $additionalDocuments !== [] ? 'Additional documents: ' . implode(', ', $additionalDocuments) : null,
        ]));

        $metadata = [
            'external_id' => $externalId,
            'notice_number' => $noticeNumber,
            'document_type' => $documentType,
            'bidding_type' => $biddingType,
            'posting_date' => $postingDate,
            'closing_date' => $closingDate,
            'purchaser_name' => $purchaserName,
            'purchaser_country' => $countryName,
            'purchaser_address' => $purchaserAddress,
            'purchaser_email' => $purchaserEmail,
            'purchaser_website' => $purchaserWebsite,
            'tender_value' => $value,
            'currency' => $currency,
            'financier' => $financier,
            'cpv' => $cpv,
            'notice_document' => $noticeDocument,
            'additional_documents' => $additionalDocuments,
        ];

        return [
            'external_id' => $externalId,
            'country_iso' => $countryIso,
            'country_name' => $countryName,
            'title' => $title,
            'source_name' => 'TendersOnTime',
            'source_url' => $sourceUrl,
            'publication_date' => $postingDate,
            'summary' => implode(' ', $summaryLines) . "\n\n[TendersOnTime JSON]\n" . json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'summary_english' => implode(' ', $summaryLines),
            'relevance_score' => 82,
            'metadata' => $metadata,
        ];
    }

    private function withMatchedKeywords(array $item): array
    {
        $matched = $this->matchedKeywords($item);
        $item['matched_keywords'] = $matched;

        if ($matched !== []) {
            $keywordLine = 'Matched 2Interact keyword(s): ' . implode(', ', $matched) . '.';
            $item['summary'] = $keywordLine . ' ' . $item['summary'];
            $item['summary_english'] = $keywordLine . ' ' . $item['summary_english'];
        }

        return $item;
    }

    private function matchedKeywords(array $item): array
    {
        $text = Str::lower(Str::ascii(implode(' ', array_filter([
            $item['title'] ?? null,
            $item['summary_english'] ?? null,
            $item['country_name'] ?? null,
            $item['metadata']['purchaser_name'] ?? null,
            $item['metadata']['purchaser_country'] ?? null,
            $item['metadata']['document_type'] ?? null,
            $item['metadata']['bidding_type'] ?? null,
            $item['metadata']['cpv'] ?? null,
        ]))));

        return $this->tenderKeywordTerms()
            ->filter(fn (string $term) => $this->textMatchesTerm($text, $term))
            ->take(10)
            ->values()
            ->all();
    }

    private function tenderKeywordTerms(): Collection
    {
        static $terms = null;

        if ($terms instanceof Collection) {
            return $terms;
        }

        $focusKeys = ['social_security', 'hrms_tenders', 'erms_tenders', 'ebpc_tenders'];
        $fallback = collect(config('country_intelligence.focuses', []))
            ->only($focusKeys)
            ->flatMap(fn (array $focus) => $focus['strong_signals'] ?? []);

        $focusTerms = collect($focusKeys)
            ->flatMap(function (string $focusKey) {
                try {
                    return ReviewFocuses::get($focusKey)['strong_signals'] ?? [];
                } catch (\Throwable) {
                    return [];
                }
            });

        $mustInclude = [
            'national provident fund',
            'national insurance',
            'social security',
            'social security administration',
            'social security administration software',
            'social insurance',
            'benefits administration',
            'pension administration',
            'pension payroll',
            'pensioner payroll',
            'benefits management',
            'payroll management',
            'human resources information system',
            'human resources management software',
            'human capital management',
            'enterprise resource planning',
            'enterprise resources planning',
            'compliance software',
            'risk management software',
            'budgeting software',
            'budget management',
            'time and attendance',
            'leave management',
            'scheduling software',
            'recruitment management software',
            'applicant tracking software',
            'talent management',
            'performance management',
            'competency management',
            'learning management',
            'training management',
            'succession planning',
            'career planning',
        ];

        $terms = $focusTerms
            ->merge($fallback)
            ->merge($mustInclude)
            ->map(fn ($term) => Str::lower(Str::ascii(trim((string) $term))))
            ->filter()
            ->reject(fn (string $term) => in_array($term, [
                'bid',
                'css',
                'hcm',
                'rfp',
                'tender',
                'tenders',
                'government',
                'procurement',
                'human resource',
                'human resources',
                'request for proposal',
                'expression of interest',
            ], true))
            ->unique()
            ->sortByDesc(fn (string $term) => strlen($term))
            ->values();

        return $terms;
    }

    private function textMatchesTerm(string $text, string $term): bool
    {
        if ($term === '') {
            return false;
        }

        if (in_array($term, ['erp', 'hris', 'hrms', 'ssas', 'erms', 'ebpc'], true)) {
            return (bool) preg_match('/(?<![a-z0-9])' . preg_quote($term, '/') . '(?![a-z0-9])/i', $text);
        }

        return Str::contains($text, $term);
    }

    private function inspectTender(array $item, bool $checkDocuments, int $timeoutSeconds): array
    {
        $metadata = $item['metadata'] ?? [];
        $documents = collect($this->documentLinks($item))
            ->map(function (array $document) use ($checkDocuments, $timeoutSeconds) {
                if ($checkDocuments) {
                    $document['check'] = $this->checkDocumentUrl((string) $document['url'], $timeoutSeconds);
                }

                return $document;
            })
            ->values()
            ->all();

        return [
            'external_id' => $item['external_id'] ?? '',
            'notice_number' => $metadata['notice_number'] ?? '',
            'country_iso' => $item['country_iso'] ?? '',
            'country_name' => $item['country_name'] ?? '',
            'title' => $item['title'] ?? '',
            'short_description' => $this->shortDescription((string) ($item['summary_english'] ?? '')),
            'posting_date' => $metadata['posting_date'] ?? $item['publication_date'] ?? '',
            'closing_date' => $metadata['closing_date'] ?? '',
            'document_type' => $metadata['document_type'] ?? '',
            'bidding_type' => $metadata['bidding_type'] ?? '',
            'purchaser_name' => $metadata['purchaser_name'] ?? '',
            'purchaser_country' => $metadata['purchaser_country'] ?? '',
            'purchaser_email' => $metadata['purchaser_email'] ?? '',
            'purchaser_website' => $metadata['purchaser_website'] ?? '',
            'tender_value' => trim((string) ($metadata['tender_value'] ?? '') . ' ' . (string) ($metadata['currency'] ?? '')),
            'financier' => $metadata['financier'] ?? '',
            'cpv' => $metadata['cpv'] ?? '',
            'source_url' => $item['source_url'] ?? '',
            'matched_keywords' => $item['matched_keywords'] ?? [],
            'documents' => $documents,
        ];
    }

    private function documentLinks(array $item): array
    {
        $metadata = $item['metadata'] ?? [];
        $links = [];

        if (filled($metadata['notice_document'] ?? null)) {
            $links[] = ['label' => 'notice_document', 'url' => (string) $metadata['notice_document']];
        }

        foreach (($metadata['additional_documents'] ?? []) as $index => $url) {
            if (filled($url)) {
                $links[] = ['label' => 'additional_document_' . ($index + 1), 'url' => (string) $url];
            }
        }

        if ($links === [] && filled($item['source_url'] ?? null)) {
            $links[] = ['label' => 'source_url', 'url' => (string) $item['source_url']];
        }

        return collect($links)
            ->unique('url')
            ->values()
            ->all();
    }

    private function checkDocumentUrl(string $url, int $timeoutSeconds): array
    {
        try {
            $response = Http::timeout(max(10, min($timeoutSeconds, 30)))->head($url);

            if ($response->status() === 405) {
                $response = Http::timeout(max(10, min($timeoutSeconds, 30)))
                    ->withHeaders(['Range' => 'bytes=0-0'])
                    ->get($url);
            }

            return [
                'ok' => $response->successful(),
                'status' => $response->status(),
                'content_type' => (string) $response->header('content-type', ''),
                'content_length' => (string) $response->header('content-length', ''),
            ];
        } catch (\Throwable $exception) {
            return [
                'ok' => false,
                'status' => null,
                'content_type' => '',
                'content_length' => '',
                'error' => Str::limit($exception->getMessage(), 180),
            ];
        }
    }

    private function shortDescription(string $summary): string
    {
        $summary = preg_replace('/\[TendersOnTime JSON\].*/s', '', $summary) ?? $summary;
        $summary = preg_replace('/^\[Official tender source\]\s*TendersOnTime tender notice\.\s*/', '', $summary) ?? $summary;
        $summary = preg_replace('/\s+/', ' ', $summary) ?? $summary;

        return Str::limit(trim($summary), 500);
    }

    private function storeTender(array $item): CountryUpdate
    {
        $country = $this->ensureCountry($item);
        $topic = $this->ensureTopic($country);
        $sourceUrl = (string) $item['source_url'];
        $fingerprint = CountryUpdateDedupeRules::sourceFingerprint($sourceUrl)
            ?: hash('sha256', 'tendersontime|' . ($item['external_id'] ?: $sourceUrl));

        $existing = CountryUpdate::query()
            ->where('country_id', $country->id)
            ->where(function ($query) use ($sourceUrl, $fingerprint, $item) {
                $query->where('source_url', $sourceUrl)
                    ->orWhere('source_fingerprint', $fingerprint);

                if (filled($item['external_id'])) {
                    $query->orWhere('summary', 'like', '%"external_id":"' . addcslashes((string) $item['external_id'], '\\%_') . '"%');
                }
            })
            ->orderByRaw("CASE WHEN review_status = 'rejected' THEN 0 WHEN map_processed_at IS NOT NULL THEN 1 WHEN archive_read_at IS NOT NULL THEN 2 ELSE 3 END")
            ->orderBy('id')
            ->first();

        $payload = [
            'country_id' => $country->id,
            'country_topic_id' => $topic->id,
            'title' => $item['title'],
            'title_english' => $item['title'],
            'title_original' => $item['title'],
            'source_name' => $item['source_name'],
            'source_url' => $sourceUrl,
            'source_fingerprint' => $fingerprint,
            'publication_date' => $item['publication_date'],
            'retrieved_at' => now(),
            'summary' => $item['summary'],
            'summary_english' => $item['summary_english'],
            'relevance_score' => $item['relevance_score'],
        ];

        if ($existing) {
            if ($existing->review_status === 'rejected' || $existing->map_processed_at || $existing->archive_read_at) {
                if (blank($existing->source_fingerprint)) {
                    $existing->forceFill(['source_fingerprint' => $fingerprint])->save();
                }

                return $existing;
            }

            $existing->fill($payload)->save();

            return $existing;
        }

        return CountryUpdate::query()->create($payload + [
            'review_status' => 'unreviewed',
        ]);
    }

    private function ensureCountry(array $item): Country
    {
        $iso = strtoupper((string) ($item['country_iso'] ?: ''));
        $name = (string) ($item['country_name'] ?: $iso ?: 'Unknown');

        if ($iso !== '') {
            return Country::query()->updateOrCreate(
                ['iso_code' => $iso],
                ['name' => $name, 'profile_status' => 'draft'],
            );
        }

        return Country::query()->firstOrCreate(
            ['name' => $name],
            ['iso_code' => null, 'profile_status' => 'draft'],
        );
    }

    private function ensureTopic(Country $country): CountryTopic
    {
        return CountryTopic::query()->updateOrCreate(
            [
                'country_id' => $country->id,
                'topic_key' => 'tendersontime_tenders',
            ],
            [
                'title' => 'TendersOnTime tender feed',
                'status' => 'monitoring',
            ],
        );
    }

    private function storeMonitorRuns(Collection $stored, Collection $items, Carbon $startedAt, string $endpoint, array $errors): void
    {
        $stored->groupBy('country_id')->each(function (Collection $updates, int $countryId) use ($items, $startedAt, $endpoint, $errors): void {
            $countryIso = Country::query()->whereKey($countryId)->value('iso_code');
            $matchedItems = $items->filter(fn (array $item) => strtoupper((string) $item['country_iso']) === strtoupper((string) $countryIso));

            CountryMonitorRun::query()->create([
                'country_id' => $countryId,
                'focus' => 'tender_rfp',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'sources_checked' => [$endpoint],
                'items_found' => $updates->filter(fn (CountryUpdate $update) => $update->wasRecentlyCreated)->count(),
                'status' => $errors === [] ? 'completed' : 'completed_with_errors',
                'error_message' => $errors === [] ? null : implode("\n", array_slice($errors, 0, 5)),
            ]);
        });
    }

    private function cleanText(mixed $value): string
    {
        $text = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<br\s*\/?>/i', ' ', $text) ?? $text;
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    private function dateOrNull(mixed $value): ?string
    {
        try {
            $value = trim((string) $value);

            return $value === '' ? null : Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
