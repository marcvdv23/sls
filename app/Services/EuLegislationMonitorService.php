<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CountryMonitorRun;
use App\Models\CountryTopic;
use App\Models\CountryUpdate;
use App\Models\IntelligenceSource;
use App\Models\KnowledgeChunk;
use App\Models\SourceDocument;
use App\Support\CountryUpdateDedupeRules;
use App\Support\EurLexDocumentClassifier;
use App\Support\EurLexTitleCleaner;
use App\Support\ReviewFocuses;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class EuLegislationMonitorService
{
    public function __construct(
        private KnowledgeChunkClassifier $classifier,
        private EurLexWebServiceClient $webServiceClient,
    )
    {
    }

    public function run(int $maxItems = 25, bool $dryRun = false, int $recentDays = 30, bool $includePdf = true, bool $saveDocuments = false): array
    {
        $startedAt = now();
        $country = $dryRun ? null : $this->ensureEuropeanUnionCountry();
        $topic = $dryRun || ! $country ? null : $this->ensureTopic($country);
        $sources = $this->sources();
        $sourceUrls = $sources->pluck('url')->filter()->unique()->values()->all();
        $items = collect();
        $errors = [];
        $sourceStats = [];

        foreach ($sources as $source) {
            try {
                $feedItems = $this->readFeed($source, $maxItems);
                $sourceStats[] = [
                    'name' => $source->name,
                    'url' => $source->url,
                    'items_read' => $feedItems->count(),
                ];
                $items = $items->merge($feedItems);

                if (! $dryRun) {
                    $source->forceFill([
                        'last_checked_at' => now(),
                        'last_success_at' => now(),
                        'last_error' => null,
                    ])->save();
                }
            } catch (Throwable $exception) {
                $errors[] = $source->name . ': ' . $exception->getMessage();
                $sourceStats[] = [
                    'name' => $source->name,
                    'url' => $source->url,
                    'items_read' => 0,
                    'error' => $exception->getMessage(),
                ];

                if (! $dryRun) {
                    $source->forceFill([
                        'last_checked_at' => now(),
                        'last_error' => $exception->getMessage(),
                    ])->save();
                }
            }
        }

        $cutoff = now()->subDays(max(1, $recentDays))->startOfDay();
        $nonEnglishItems = $items
            ->filter(fn (array $item) => $this->isNonEnglishOnlyCorrigendum($item))
            ->values();

        if (! $dryRun && $country && $nonEnglishItems->isNotEmpty()) {
            $this->rejectNonEnglishItems($country, $nonEnglishItems);
        }

        $items = $items
            ->reject(fn (array $item) => $this->isNonEnglishOnlyCorrigendum($item))
            ->filter(fn (array $item) => $this->isLegislationItem($item))
            ->filter(fn (array $item) => blank($item['publication_date'] ?? null) || Carbon::parse($item['publication_date'])->greaterThanOrEqualTo($cutoff))
            ->unique(fn (array $item) => $item['celex'] ?: $item['source_url'])
            ->sortByDesc(fn (array $item) => $item['publication_date'] ?? '')
            ->take($maxItems)
            ->values();

        $stored = collect();

        if (! $dryRun && $country) {
            $stored = $items->map(fn (array $item) => $this->storeLegislationItem($country, $topic, $item, $includePdf, $saveDocuments));

            CountryMonitorRun::query()->create([
                'country_id' => $country->id,
                'focus' => 'legislation',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'sources_checked' => $sourceUrls,
                'items_found' => $stored->filter(fn (CountryUpdate $update) => $update->wasRecentlyCreated)->count(),
                'status' => $errors === [] ? 'completed' : 'completed_with_errors',
                'error_message' => $errors === [] ? null : implode("\n", array_slice($errors, 0, 5)),
            ]);
        }

        return [
            'source_count' => $sources->count(),
            'sources_checked' => $sourceUrls,
            'items_found' => $items->count(),
            'stored_count' => $stored->count(),
            'errors' => $errors,
            'items' => $items->all(),
            'source_stats' => $sourceStats,
        ];
    }

    public function runWebserviceBackfill(
        string $fromDate,
        string $toDate,
        int $pageSize = 25,
        int $maxPages = 1,
        bool $dryRun = false,
        bool $includePdf = true,
        bool $saveDocuments = false,
        string $language = 'en',
        ?string $expertQuery = null,
        array $queryTerms = [],
        ?string $endpointUrl = null,
        ?string $username = null,
        ?string $password = null,
        int $pageDelaySeconds = 10,
    ): array {
        $startedAt = now();
        $country = $dryRun ? null : $this->ensureEuropeanUnionCountry();
        $topic = $dryRun || ! $country ? null : $this->ensureTopic($country);
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->endOfDay();
        $expertQuery ??= $this->backfillExpertQuery($from, $to, $queryTerms);
        $items = collect();
        $pages = [];
        $errors = [];
        $totalHits = 0;

        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            if ($page > 1 && $pageDelaySeconds > 0) {
                sleep(min(120, $pageDelaySeconds));
            }

            try {
                $result = $this->webServiceClient->search(
                    expertQuery: $expertQuery,
                    page: $page,
                    pageSize: max(1, min(1000, $pageSize)),
                    language: $language,
                    excludeAllConsleg: true,
                    limitToLatestConsleg: false,
                    endpointUrl: $endpointUrl,
                    username: $username,
                    password: $password,
                );
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
                break;
            }

            $pages[] = [
                'page' => $page,
                'num_hits' => $result['num_hits'] ?? 0,
                'total_hits' => $result['total_hits'] ?? 0,
            ];
            $totalHits = max($totalHits, (int) ($result['total_hits'] ?? 0));

            $pageItems = collect($result['items'] ?? []);
            if ($pageItems->isEmpty()) {
                break;
            }

            $items = $items->merge($pageItems);

            if ($totalHits > 0 && $items->count() >= $totalHits) {
                break;
            }
        }

        $nonEnglishItems = $items
            ->filter(fn (array $item) => $this->isNonEnglishOnlyCorrigendum($item))
            ->values();

        if (! $dryRun && $country && $nonEnglishItems->isNotEmpty()) {
            $this->rejectNonEnglishItems($country, $nonEnglishItems);
        }

        $items = $items
            ->reject(fn (array $item) => $this->isNonEnglishOnlyCorrigendum($item))
            ->filter(fn (array $item) => $this->isLegislationItem($item))
            ->filter(fn (array $item) => blank($item['publication_date'] ?? null) || Carbon::parse($item['publication_date'])->betweenIncluded($from, $to))
            ->unique(fn (array $item) => $item['celex'] ?: $item['source_url'])
            ->sortByDesc(fn (array $item) => $item['publication_date'] ?? '')
            ->values();

        $stored = collect();

        if (! $dryRun && $country && $topic) {
            $stored = $items->map(fn (array $item) => $this->storeLegislationItem($country, $topic, $item, $includePdf, $saveDocuments));

            CountryMonitorRun::query()->create([
                'country_id' => $country->id,
                'focus' => 'legislation',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'sources_checked' => array_filter([$endpointUrl ?: env('EURLEX_WEBSERVICE_ENDPOINT', 'https://eur-lex.europa.eu/EURLexWebService')]),
                'items_found' => $stored->filter(fn (CountryUpdate $update) => $update->wasRecentlyCreated)->count(),
                'status' => $errors === [] ? 'completed' : 'completed_with_errors',
                'error_message' => $errors === [] ? null : implode("\n", array_slice($errors, 0, 5)),
            ]);
        }

        return [
            'expert_query' => $expertQuery,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'pages' => $pages,
            'total_hits' => $totalHits,
            'items_found' => $items->count(),
            'stored_count' => $stored->count(),
            'errors' => $errors,
            'items' => $items->all(),
        ];
    }

    public function importPastedAlertText(string $text, string $sourceName = 'EUR-Lex pasted RSS alert', bool $dryRun = false, ?int $limit = null): array
    {
        $startedAt = now();
        $items = $this->parsePastedAlertText($text);

        if ($limit !== null && $limit > 0) {
            $items = $items->take($limit)->values();
        }

        $items = $items
            ->reject(fn (array $item) => $this->isNonEnglishOnlyCorrigendum($item))
            ->unique(fn (array $item) => $item['celex'] ?: $item['source_url'])
            ->sortByDesc(fn (array $item) => $item['publication_date'] ?? '')
            ->values();

        $stored = collect();
        $country = $dryRun ? null : $this->ensureEuropeanUnionCountry();
        $topic = $dryRun || ! $country ? null : $this->ensureTopic($country);

        if (! $dryRun && $country && $topic) {
            $stored = $items->map(function (array $item) use ($country, $topic, $sourceName) {
                $item['source_name'] = $sourceName;

                return $this->storeLegislationItem($country, $topic, $item, includePdf: false, saveDocuments: false);
            });

            CountryMonitorRun::query()->create([
                'country_id' => $country->id,
                'focus' => 'legislation',
                'started_at' => $startedAt,
                'finished_at' => now(),
                'sources_checked' => [$sourceName],
                'items_found' => $stored->filter(fn (CountryUpdate $update) => $update->wasRecentlyCreated)->count(),
                'status' => 'completed',
            ]);
        }

        return [
            'items_found' => $items->count(),
            'stored_count' => $stored->count(),
            'items' => $items->all(),
        ];
    }

    private function sources(): Collection
    {
        if (! Schema::hasTable('intelligence_sources')) {
            return collect();
        }

        return IntelligenceSource::query()
            ->where('is_enabled', true)
            ->where('focus', 'legislation')
            ->whereIn('connector', ['eurlex_official_journal_l', 'eurlex_rss'])
            ->orderBy('name')
            ->get();
    }

    private function backfillExpertQuery(Carbon $from, Carbon $to, array $queryTerms = []): string
    {
        $genericLawTerms = [
            'celex',
            'decision',
            'decision (eu)',
            'directive',
            'directive (eu)',
            'eur-lex',
            'legislation',
            'official journal',
            'official journal of the european union',
            'regulation',
            'regulation (eu)',
        ];

        if ($queryTerms === [] && ReviewFocuses::has('legislation')) {
            $focus = ReviewFocuses::get('legislation') ?? [];
            $queryTerms = collect($focus['terms'] ?? [])
                ->merge($focus['strong_signals'] ?? [])
                ->all();
        }

        $terms = collect($queryTerms ?: [
            'environment',
            'climate',
            'sustainability',
            'emissions',
            'carbon',
            'energy',
            'waste',
            'water',
            'biodiversity',
            'circular economy',
            'due diligence',
            'esg',
        ])
            ->map(fn ($term) => Str::lower(trim((string) $term)))
            ->filter()
            ->reject(fn (string $term) => in_array($term, $genericLawTerms, true))
            ->unique()
            ->values();

        $textQuery = $terms
            ->map(fn (string $term) => 'Text ~ ' . $this->expertQueryValue($term))
            ->implode(' OR ');

        $dateQuery = sprintf(
            'DD >= %s AND DD <= %s',
            $from->format('d/m/Y'),
            $to->format('d/m/Y'),
        );

        $documentTypeQuery = '(DN = 3*R* OR DN = 3*L* OR DN = 3*D*)';

        return trim(sprintf(
            'SELECT DN,TI_DISPLAY,DD WHERE %s AND %s%s ORDER BY DD DESC',
            $dateQuery,
            $documentTypeQuery,
            $textQuery !== '' ? ' AND (' . $textQuery . ')' : '',
        ));
    }

    private function expertQueryValue(string $value): string
    {
        $value = trim(str_replace('"', ' ', $value));

        return str_contains($value, ' ') ? '"' . $value . '"' : $value;
    }

    private function readFeed(IntelligenceSource $source, int $maxItems): Collection
    {
        $response = Http::timeout(30)
            ->accept('application/xml,text/xml,*/*')
            ->get((string) $source->url);

        $response->throw();

        $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

        if (! $xml) {
            return collect();
        }

        return collect($xml->channel->item ?? [])
            ->take(max(1, $maxItems))
            ->map(function (\SimpleXMLElement $item) use ($source) {
                $title = trim((string) $item->title);
                $link = trim((string) $item->link);
                $description = $this->plainText((string) $item->description);
                $celex = $this->extractCelex($title . ' ' . $link . ' ' . $description);
                $classification = EurLexDocumentClassifier::classify($celex, $title . ' ' . $description, (string) $source->name, $link);

                return [
                    'title' => $title,
                    'celex' => $celex,
                    'legal_document_code' => $classification['legal_document_code'],
                    'legal_instrument_type' => $classification['legal_instrument_type'],
                    'legislation_stage' => $classification['legislation_stage'],
                    'source_url' => $this->eurlexUrl($celex, $link, 'HTML'),
                    'pdf_url' => $celex ? $this->eurlexUrl($celex, $link, 'PDF') : null,
                    'xml_url' => $celex ? $this->eurlexUrl($celex, $link, 'XML') : null,
                    'source_name' => $source->name,
                    'source_connector' => $source->connector,
                    'source_feed_url' => $source->url,
                    'publication_date' => $this->parseDate((string) $item->pubDate)?->toDateString(),
                    'summary' => $description,
                    'raw_match_text' => trim($title . ' ' . $description . ' CELEX ' . $celex),
                ];
            })
            ->filter(fn (array $item) => filled($item['source_url']) && filled($item['title']))
            ->values();
    }

    private function parsePastedAlertText(string $text): Collection
    {
        $items = collect();
        $current = null;
        $lines = preg_split('/\R/u', str_replace("\xC2\xA0", ' ', $text)) ?: [];

        $finish = function () use (&$current, &$items): void {
            if (! $current || blank($current['title'] ?? null)) {
                $current = null;

                return;
            }

            $identifier = (string) ($current['identifier'] ?? '');
            $title = Str::squish((string) $current['title']);
            $celex = str_starts_with($identifier, 'CELEX:') ? Str::after($identifier, 'CELEX:') : null;
            $sourceUrl = $this->sourceUrlForIdentifier($identifier);
            $author = Str::squish((string) ($current['author'] ?? ''));
            $classification = EurLexDocumentClassifier::classify($celex, $title, 'EUR-Lex pasted RSS alert', $sourceUrl);
            $summaryParts = array_filter([
                $identifier ? 'Identifier: ' . $identifier . '.' : null,
                $author ? 'Author(s): ' . $author . '.' : null,
                'Imported from a EUR-Lex RSS alert/search result. Full legal document has not been retrieved or archived.',
            ]);

            $items->push([
                'title' => $title,
                'celex' => $celex,
                'legal_document_code' => $classification['legal_document_code'],
                'legal_instrument_type' => $classification['legal_instrument_type'],
                'legislation_stage' => $classification['legislation_stage'],
                'source_url' => $sourceUrl,
                'pdf_url' => null,
                'xml_url' => null,
                'source_name' => 'EUR-Lex pasted RSS alert',
                'source_connector' => 'eurlex_pasted_alert',
                'source_feed_url' => null,
                'publication_date' => $current['publication_date'] ?? null,
                'summary' => implode(' ', $summaryParts),
                'raw_match_text' => Str::squish($identifier . ' ' . $title . ' ' . $author),
            ]);

            $current = null;
        };

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^((?:CELEX|CONSIL|PI_COM|PI_EESC):[^:]+):\s*(.+)$/u', $line, $matches) === 1) {
                $finish();

                $current = [
                    'identifier' => trim($matches[1]),
                    'title' => trim($matches[2]),
                    'publication_date' => null,
                    'author' => null,
                ];

                continue;
            }

            if (! $current) {
                continue;
            }

            if (preg_match('/^Publication date\s*:\s*(.+)$/iu', $line, $matches) === 1) {
                $current['publication_date'] = $this->parseDate($matches[1])?->toDateString();

                continue;
            }

            if (preg_match('/^Author\(s\)\s*:\s*(.+)$/iu', $line, $matches) === 1) {
                $current['author'] = trim($matches[1]);

                continue;
            }

            $current['title'] = trim(($current['title'] ?? '') . ' ' . $line);
        }

        $finish();

        return $items;
    }

    private function storeLegislationItem(Country $country, CountryTopic $topic, array $item, bool $includePdf, bool $saveDocuments): CountryUpdate
    {
        return DB::transaction(function () use ($country, $topic, $item, $includePdf, $saveDocuments) {
            $sourceUrl = (string) $item['source_url'];
            $fingerprint = CountryUpdateDedupeRules::sourceFingerprint($sourceUrl);

            $update = CountryUpdate::query()
                ->where('country_id', $country->id)
                ->where(function ($query) use ($sourceUrl, $fingerprint) {
                    $query->where('source_url', $sourceUrl);

                    if ($fingerprint !== null) {
                        $query->orWhere('source_fingerprint', $fingerprint);
                    }
                })
                ->first();

            if (! $update) {
                $update = CountryUpdate::query()
                    ->where('country_id', $country->id)
                    ->where('source_name', (string) $item['source_name'])
                    ->where('title', Str::limit((string) $item['title'], 500, ''))
                    ->first();
            }

            $htmlText = $saveDocuments ? $this->retrieveLegalText($sourceUrl) : '';
            $displayTitle = $this->displayTitleFor($item, $htmlText);
            $summary = $this->summaryFor($item);
            $classification = EurLexDocumentClassifier::classify(
                $item['celex'] ?? $item['legal_document_code'] ?? null,
                $displayTitle . ' ' . ((string) ($item['raw_match_text'] ?? $item['summary'] ?? '')),
                (string) ($item['source_name'] ?? ''),
                $sourceUrl,
            );
            $payload = [
                'country_id' => $country->id,
                'country_topic_id' => $topic->id,
                'title' => Str::limit($displayTitle, 500, ''),
                'title_english' => Str::limit($displayTitle, 500, ''),
                'title_original' => Str::limit((string) $item['title'], 500, ''),
                'source_name' => Str::limit((string) $item['source_name'], 255, ''),
                'source_url' => $sourceUrl,
                'source_fingerprint' => $fingerprint,
                'legal_document_code' => $classification['legal_document_code'],
                'legal_instrument_type' => $classification['legal_instrument_type'],
                'legislation_stage' => $classification['legislation_stage'],
                'publication_date' => $item['publication_date'] ?? null,
                'retrieved_at' => now(),
                'summary' => $summary,
                'summary_english' => $summary,
                'relevance_score' => $this->relevanceScore((string) ($item['raw_match_text'] ?? $item['title'])),
            ];

            if ($update) {
                if (! in_array($update->review_status, ['rejected'], true) && ! $update->map_processed_at && ! $update->archive_read_at) {
                    $update->fill($payload)->save();
                }
            } else {
                $update = CountryUpdate::query()->create($payload + [
                    'review_status' => 'unreviewed',
                ]);
            }

            if ($saveDocuments && blank($update->source_document_id)) {
                $document = $this->storeSourceDocument($country, $item, $includePdf, $htmlText, $displayTitle);
                $update->forceFill(['source_document_id' => $document->id])->save();
            } elseif ($saveDocuments && $update->sourceDocument) {
                $update->sourceDocument->forceFill([
                    'title' => Str::limit($displayTitle, 250, ''),
                    'source_url' => $sourceUrl,
                    'source_date' => $item['publication_date'] ?? null,
                    'retrieved_at' => now(),
                ])->save();
            }

            return $update;
        });
    }

    private function storeSourceDocument(Country $country, array $item, bool $includePdf, ?string $htmlText = null, ?string $displayTitle = null): SourceDocument
    {
        $celex = (string) ($item['celex'] ?? '');
        $htmlText ??= $this->retrieveLegalText((string) ($item['source_url'] ?? ''));
        $displayTitle ??= $this->displayTitleFor($item, $htmlText);
        $pdfPath = null;
        $pdfUrl = (string) ($item['pdf_url'] ?? '');

        if ($includePdf && $pdfUrl !== '') {
            $pdfPath = $this->archivePdf($pdfUrl, $celex);
        }

        $textPath = null;
        if ($htmlText !== '') {
            $textPath = 'knowledge-sources/eurlex/' . ($celex ?: Str::slug((string) $item['title'])) . '.txt';
            Storage::put($textPath, $htmlText);
        }

        $document = SourceDocument::query()->updateOrCreate(
            ['source_url' => (string) $item['source_url']],
            [
                'title' => Str::limit($displayTitle, 250, ''),
                'source_type' => 'law',
                'intake_category' => 'legislation',
                'intake_action' => 'review',
                'related_country_id' => $country->id,
                'language_code' => 'en',
                'original_filename' => $pdfPath ? basename($pdfPath) : ($textPath ? basename($textPath) : null),
                'storage_path' => $pdfPath ?: $textPath,
                'source_date' => $item['publication_date'] ?? null,
                'retrieved_at' => now(),
                'approval_status' => 'unreviewed',
                'approved_for_proposals' => false,
                'intake_notes' => trim(implode("\n", array_filter([
                    $celex ? 'CELEX: ' . $celex : null,
                    $pdfUrl ? 'Official PDF: ' . $pdfUrl : null,
                    filled($item['xml_url'] ?? null) ? 'EUR-Lex XML: ' . $item['xml_url'] : null,
                ]))),
            ]
        );

        if ($htmlText !== '') {
            $this->replaceChunks($document, $country, $htmlText);
        }

        return $document;
    }

    private function retrieveLegalText(string $url): string
    {
        if ($url === '') {
            return '';
        }

        try {
            $response = Http::timeout(30)->accept('text/html,application/xhtml+xml,*/*')->get($url);
            $response->throw();

            return $this->plainText($response->body());
        } catch (Throwable) {
            return '';
        }
    }

    private function archivePdf(string $url, string $celex): ?string
    {
        try {
            $response = Http::timeout(45)->accept('application/pdf,*/*')->get($url);
            $response->throw();

            $body = $response->body();
            $contentType = strtolower($response->header('content-type', ''));

            if (! Str::contains($contentType, 'application/pdf') && ! str_starts_with($body, '%PDF')) {
                return null;
            }

            $path = 'knowledge-sources/eurlex/' . ($celex ?: sha1($url)) . '.pdf';
            Storage::put($path, $body);

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    private function replaceChunks(SourceDocument $document, Country $country, string $text): void
    {
        $document->chunks()->delete();

        foreach ($this->chunkText($text) as $index => $chunkText) {
            $chunkTitle = Str::limit($document->title . ' - legislation text ' . ($index + 1), 250, '');
            $classification = $this->classifier->classify($chunkTitle, $chunkText, 'law');

            KnowledgeChunk::query()->create([
                'source_document_id' => $document->id,
                'country_id' => $country->id,
                'chunk_title' => $chunkTitle,
                'chunk_text' => $chunkText,
                'citation_label' => Str::limit($document->title . ', EUR-Lex', 250, ''),
                ...$classification,
                'approval_status' => 'unreviewed',
            ]);
        }
    }

    private function ensureEuropeanUnionCountry(): Country
    {
        return Country::query()->updateOrCreate(
            ['iso_code' => 'EU'],
            [
                'name' => 'European Union',
                'region' => 'Europe',
                'default_language_code' => 'en',
                'profile_status' => 'draft',
            ]
        );
    }

    private function ensureTopic(Country $country): CountryTopic
    {
        return CountryTopic::query()->updateOrCreate(
            ['country_id' => $country->id, 'topic' => 'European Union legislation'],
            ['tracking_status' => 'active']
        );
    }

    private function isLegislationItem(array $item): bool
    {
        $text = Str::lower((string) ($item['raw_match_text'] ?? ''));
        $hasOfficialSource = filled($item['celex'] ?? null)
            || Str::contains(Str::lower((string) ($item['source_name'] ?? '')), ['official journal', 'eur-lex'])
            || Str::contains(Str::lower((string) ($item['source_connector'] ?? '')), ['eurlex_official_journal', 'eurlex_rss']);
        $hasOfficialLawSignal = $hasOfficialSource && Str::contains($text, [
            'regulation',
            'directive',
            'decision',
            'official journal',
            'oj:l_',
        ]);

        if (! $hasOfficialLawSignal) {
            return false;
        }

        if (ReviewFocuses::has('legislation')) {
            $focus = ReviewFocuses::get('legislation') ?? [];
            $genericLawTerms = [
                'celex',
                'decision',
                'decision (eu)',
                'directive',
                'directive (eu)',
                'eur-lex',
                'legislation',
                'official journal',
                'official journal of the european union',
                'regulation',
                'regulation (eu)',
            ];
            $terms = collect($focus['terms'] ?? [])
                ->merge($focus['strong_signals'] ?? [])
                ->map(fn ($term) => Str::lower(trim((string) $term)))
                ->filter()
                ->reject(fn (string $term) => in_array($term, $genericLawTerms, true))
                ->unique();

            if ($terms->isNotEmpty() && $terms->contains(fn (string $term) => Str::contains($text, $term))) {
                return true;
            }
        }

        return Str::contains($text, [
            'biodiversity',
            'carbon',
            'circular economy',
            'climate',
            'due diligence',
            'emission',
            'energy',
            'environment',
            'esg',
            'greenhouse',
            'pollution',
            'renewable',
            'sustainability',
            'waste',
            'water',
        ]);
    }

    private function isNonEnglishOnlyCorrigendum(array $item): bool
    {
        $text = Str::lower(implode(' ', [
            $item['title'] ?? '',
            $item['summary'] ?? '',
            $item['raw_match_text'] ?? '',
        ]));

        return Str::contains($text, [
            'does not concern the english version',
            'does not affect the english version',
            'no afecta a la versión inglesa',
            'non concerne la version anglaise',
        ]);
    }

    private function rejectNonEnglishItems(Country $country, Collection $items): void
    {
        $items->each(function (array $item) use ($country): void {
            CountryUpdate::query()
                ->where('country_id', $country->id)
                ->where('review_status', '!=', 'rejected')
                ->where(function ($query) use ($item) {
                    $sourceUrl = (string) ($item['source_url'] ?? '');
                    $celex = (string) ($item['celex'] ?? '');
                    $title = Str::limit((string) ($item['title'] ?? ''), 500, '');

                    if ($sourceUrl !== '') {
                        $query->orWhere('source_url', $sourceUrl);
                    }

                    if ($celex !== '') {
                        $query->orWhere('summary', 'like', '%' . addcslashes('CELEX: ' . $celex, '\\%_') . '%')
                            ->orWhere('title', 'like', '%' . addcslashes($celex, '\\%_') . '%')
                            ->orWhere('source_url', 'like', '%' . addcslashes($celex, '\\%_') . '%');
                    }

                    if ($title !== '') {
                        $query->orWhere('title_original', $title);
                    }
                })
                ->update([
                    'review_status' => 'rejected',
                    'rejection_reason_code' => 'not_relevant',
                    'rejection_reason' => 'Skipped by EU legislation monitor because the EUR-Lex corrigendum does not concern the English version.',
                    'rejected_at' => now(),
                ]);
        });
    }

    private function summaryFor(array $item): string
    {
        $focusLabel = ReviewFocuses::get('legislation')['label'] ?? 'Legislation';
        $parts = array_filter([
            '[' . $focusLabel . '] ' . $this->legislationMetadataLabel($item) . ' from EUR-Lex.',
            filled($item['celex'] ?? null) ? 'CELEX: ' . $item['celex'] . '.' : null,
            filled($item['summary'] ?? null) ? Str::limit($this->plainText((string) $item['summary']), 700) : null,
        ]);

        return implode(' ', $parts);
    }

    private function legislationMetadataLabel(array $item): string
    {
        $type = str_replace('_', ' ', (string) ($item['legal_instrument_type'] ?? 'legislation'));
        $stage = str_replace('_', ' ', (string) ($item['legislation_stage'] ?? ''));

        if ($type === '') {
            $type = 'legislation';
        }

        return trim($stage !== '' && $stage !== 'other'
            ? Str::title($stage) . ' ' . $type
            : Str::title($type));
    }

    private function displayTitleFor(array $item, string $htmlText = ''): string
    {
        $legalTitle = $this->extractLegalTitle($htmlText);

        if ($legalTitle !== '') {
            return $legalTitle;
        }

        $summary = EurLexTitleCleaner::clean($this->plainText((string) ($item['summary'] ?? '')));
        if ($summary !== '' && ! $this->isIdentifierOnlyTitle($summary)) {
            return Str::limit($summary, 500, '');
        }

        $title = EurLexTitleCleaner::clean((string) ($item['title'] ?? ''));
        if ($title !== '' && ! $this->isIdentifierOnlyTitle($title)) {
            return $title;
        }

        $rawMatchTitle = EurLexTitleCleaner::clean((string) ($item['raw_match_text'] ?? ''));
        if ($rawMatchTitle !== '' && ! $this->isIdentifierOnlyTitle($rawMatchTitle)) {
            return Str::limit($rawMatchTitle, 500, '');
        }

        return EurLexTitleCleaner::fallbackTitle(
            $item['legal_document_code'] ?? $item['celex'] ?? null,
            $item['legal_instrument_type'] ?? null,
        );
    }

    private function extractLegalTitle(string $text): string
    {
        $text = $this->plainText($text);

        if ($text === '') {
            return '';
        }

        $patterns = [
            '/(Corrigendum to [^.]{20,500}?(?:Regulation|Directive|Decision)[^.]{20,500}?)(?= THE EUROPEAN| HAS ADOPTED|$)/iu',
            '/((?:Commission\s+)?(?:Delegated\s+|Implementing\s+)?(?:Regulation|Directive|Decision)\s+\(EU\)[^.]{20,500}?)(?= THE EUROPEAN| HAS ADOPTED|$)/iu',
            '/((?:Regulation|Directive|Decision)\s+\(EU\)[^.]{20,500}?)(?= THE EUROPEAN| HAS ADOPTED|$)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches) === 1) {
                return Str::limit(Str::squish(trim($matches[1])), 500, '');
            }
        }

        return '';
    }

    private function isIdentifierOnlyTitle(string $value): bool
    {
        $value = trim($value);

        return $value === '' || preg_match('/^(?:CELEX:)?[0-9A-Z]+(?:\([0-9A-Z]+\))?$/i', $value) === 1;
    }

    private function relevanceScore(string $text): float
    {
        $text = Str::lower($text);
        $score = 2.0;

        foreach (['environment', 'climate', 'sustainability', 'energy', 'emission', 'carbon', 'waste', 'water', 'biodiversity', 'circular economy', 'due diligence', 'esg'] as $term) {
            if (Str::contains($text, $term)) {
                $score += 1.0;
            }
        }

        foreach (['regulation', 'directive', 'decision', 'official journal', 'celex'] as $term) {
            if (Str::contains($text, $term)) {
                $score += 0.5;
            }
        }

        return min(10.0, $score);
    }

    private function eurlexUrl(?string $celex, string $fallback, string $format): string
    {
        if ($celex) {
            return 'https://eur-lex.europa.eu/legal-content/EN/TXT/' . $format . '/?uri=CELEX:' . rawurlencode($celex);
        }

        return $fallback;
    }

    private function sourceUrlForIdentifier(string $identifier): string
    {
        if (str_starts_with($identifier, 'CELEX:')) {
            return 'https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:' . rawurlencode(Str::after($identifier, 'CELEX:'));
        }

        return 'https://eur-lex.europa.eu/search.html?scope=EURLEX&text=' . rawurlencode($identifier) . '&lang=en&type=quick';
    }

    private function extractCelex(string $value): ?string
    {
        return preg_match('/CELEX:([0-9A-Z]+(?:\([0-9A-Z]+\))?)/i', $value, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return trim($value) !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function plainText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($clean === '') {
            return [];
        }

        return collect(Str::of($clean)->split('/(?<=\.|\?|!)\s+/'))
            ->reduce(function (array $chunks, string $sentence) {
                $sentence = trim($sentence);

                if ($sentence === '') {
                    return $chunks;
                }

                $lastIndex = count($chunks) - 1;

                if ($lastIndex < 0 || strlen($chunks[$lastIndex] . ' ' . $sentence) > 1800) {
                    $chunks[] = $sentence;
                } else {
                    $chunks[$lastIndex] .= ' ' . $sentence;
                }

                return $chunks;
            }, []);
    }
}
