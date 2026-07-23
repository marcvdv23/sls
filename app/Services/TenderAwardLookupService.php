<?php

namespace App\Services;

use App\Models\CountryUpdate;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TenderAwardLookupService
{
    /**
     * @return array{status:string,title:?string,url:?string,source_name:?string,award_date:?string,context:?string,checked_at:\Carbon\CarbonInterface}
     */
    public function check(CountryUpdate $update): array
    {
        $update->loadMissing('country');

        $result = $this->lookupBySource($update);

        $update->forceFill([
            'award_status' => $result['status'],
            'award_checked_at' => $result['checked_at'],
            'award_title' => $result['title'],
            'award_url' => $result['url'],
            'award_source_name' => $result['source_name'],
            'award_date' => $result['award_date'],
            'award_context' => $result['context'],
        ])->save();

        return $result;
    }

    private function lookupBySource(CountryUpdate $update): array
    {
        $host = Str::of(parse_url((string) $update->source_url, PHP_URL_HOST) ?: '')
            ->lower()
            ->replace('www.', '')
            ->toString();

        if (Str::contains($host, 'worldbank.org')) {
            return $this->lookupWorldBankAward($update);
        }

        if (Str::contains($host, ['iadb.org', 'iadbdocs.iadb.org'])) {
            return $this->lookupIdbAward($update);
        }

        return $this->unknownResult('Award lookup is not configured for this source yet.');
    }

    private function lookupWorldBankAward(CountryUpdate $update): array
    {
        $endpoint = (string) config('country_intelligence.world_bank_procurement_endpoint', '');

        if ($endpoint === '') {
            return $this->unknownResult('World Bank procurement endpoint is not configured.');
        }

        $request = Http::timeout(20)->connectTimeout(8)->retry(2, 500)->acceptJson();

        if (! config('country_intelligence.verify_ssl', false)) {
            $request = $request->withoutVerifying();
        }

        $queries = $this->awardSearchQueries($update);
        $items = collect();

        foreach ($queries as $query) {
            try {
                $response = $request->get($endpoint, [
                    'format' => 'json',
                    'apilang' => 'en',
                    'srce' => 'both',
                    'rows' => 20,
                    'os' => 0,
                    'qterm' => $query,
                ]);
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if (! $response->ok()) {
                continue;
            }

            $items = $items->merge(collect($response->json('procnotices', [])));
        }

        $best = $items
            ->map(fn (array $notice) => $this->worldBankAwardCandidate($notice, $update))
            ->filter()
            ->sortByDesc('score')
            ->first();

        if (! $best) {
            return $this->notFoundResult('No award or contract-award notice was found in World Bank procurement records for this tender yet.');
        }

        return [
            'status' => 'awarded',
            'title' => $best['title'],
            'url' => $best['url'],
            'source_name' => 'World Bank Procurement',
            'award_date' => $best['award_date'],
            'context' => $best['context'],
            'checked_at' => now(),
        ];
    }

    private function lookupIdbAward(CountryUpdate $update): array
    {
        $text = Str::lower((string) $update->title . ' ' . (string) $update->summary);

        if (Str::contains($text, ['award', 'awarded', 'contract awarded', 'notice of award'])) {
            return [
                'status' => 'awarded',
                'title' => $update->title_english ?: $update->title,
                'url' => $update->source_url,
                'source_name' => $update->source_name ?: 'Inter-American Development Bank Procurement',
                'award_date' => $update->publication_date?->toDateString(),
                'context' => 'The source record itself appears to be an award or contract-award notice.',
                'checked_at' => now(),
            ];
        }

        return $this->notFoundResult('No separate IDB award record has been found yet for this tender. IDB-specific award search needs source API support beyond the source PDF.');
    }

    private function awardSearchQueries(CountryUpdate $update): Collection
    {
        $title = $this->compactTenderTitle((string) ($update->title_english ?: $update->title));
        $opId = $this->worldBankProcurementId((string) $update->source_url);
        $country = (string) ($update->country?->name ?? '');

        return collect([
            $opId,
            $title . ' awarded',
            $title . ' contract award',
            $title . ' notice of award',
            $country . ' ' . $this->importantPhrase($title) . ' awarded',
            $country . ' ' . $this->importantPhrase($title) . ' contract',
        ])
            ->map(fn (?string $query) => trim((string) $query))
            ->filter(fn (string $query) => $query !== '')
            ->unique()
            ->values();
    }

    private function worldBankAwardCandidate(array $notice, CountryUpdate $update): ?array
    {
        $title = trim((string) Arr::get($notice, 'bid_description', ''))
            ?: trim((string) Arr::get($notice, 'notice_type', '') . ' - ' . (string) Arr::get($notice, 'project_name', ''));
        $noticeType = (string) Arr::get($notice, 'notice_type', '');
        $noticeText = implode(' ', array_filter([
            $title,
            $noticeType,
            (string) Arr::get($notice, 'notice_text', ''),
            (string) Arr::get($notice, 'project_name', ''),
            (string) Arr::get($notice, 'procurement_method_name', ''),
        ]));
        $lower = Str::lower($noticeText);

        if (! Str::contains($lower, ['award', 'awarded', 'contract award', 'notice of award', 'winning bidder', 'winner'])) {
            return null;
        }

        $score = $this->titleOverlapScore((string) ($update->title_english ?: $update->title), $noticeText);

        if ($update->country && Str::contains(Str::lower((string) Arr::get($notice, 'project_ctry_name', '')), Str::lower($update->country->name))) {
            $score += 3;
        }

        if ($score < 4) {
            return null;
        }

        $id = (string) Arr::get($notice, 'id', '');
        $url = $id !== ''
            ? 'https://projects.worldbank.org/en/projects-operations/procurement-detail/' . rawurlencode($id)
            : (string) $update->source_url;

        return [
            'title' => Str::limit($title, 500, ''),
            'url' => $url,
            'award_date' => $this->parseDate((string) Arr::get($notice, 'noticedate', Arr::get($notice, 'submission_date', ''))),
            'context' => Str::limit($noticeText, 1000, ''),
            'score' => $score,
        ];
    }

    private function compactTenderTitle(string $title): string
    {
        $title = preg_replace('/\b(request for bids?|rfb|icb|consultancy services?|procurement of|hiring of)\b/i', ' ', $title);
        $title = preg_replace('/[^A-Za-z0-9\s-]/', ' ', $title);

        return Str::limit(trim(preg_replace('/\s+/', ' ', $title) ?? ''), 120, '');
    }

    private function importantPhrase(string $title): string
    {
        $words = collect(preg_split('/\s+/', Str::lower($title)) ?: [])
            ->map(fn (string $word) => trim($word, '-_.,;:()[]{}'))
            ->filter(fn (string $word) => strlen($word) >= 4)
            ->reject(fn (string $word) => in_array($word, ['system', 'services', 'service', 'provider', 'implementation', 'development', 'management', 'technical', 'assistance'], true))
            ->take(5)
            ->values();

        return $words->implode(' ');
    }

    private function titleOverlapScore(string $originalTitle, string $candidateText): int
    {
        $originalWords = collect(preg_split('/\s+/', Str::lower($originalTitle)) ?: [])
            ->map(fn (string $word) => trim($word, '-_.,;:()[]{}'))
            ->filter(fn (string $word) => strlen($word) >= 5)
            ->unique();
        $candidate = Str::lower($candidateText);

        return $originalWords
            ->filter(fn (string $word) => Str::contains($candidate, $word))
            ->count();
    }

    private function worldBankProcurementId(string $url): string
    {
        if (preg_match('/(OP\d+)/i', $url, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }

    private function parseDate(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function notFoundResult(string $context): array
    {
        return [
            'status' => 'not_found',
            'title' => null,
            'url' => null,
            'source_name' => null,
            'award_date' => null,
            'context' => $context,
            'checked_at' => now(),
        ];
    }

    private function unknownResult(string $context): array
    {
        return [
            'status' => 'unknown',
            'title' => null,
            'url' => null,
            'source_name' => null,
            'award_date' => null,
            'context' => $context,
            'checked_at' => now(),
        ];
    }
}
