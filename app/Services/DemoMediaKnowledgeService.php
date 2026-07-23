<?php

namespace App\Services;

use App\Models\DemoFeatureMoment;
use App\Models\DemoFrame;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoMediaKnowledgeService
{
    /**
     * @return Collection<int, array{moment: DemoFeatureMoment, frames: Collection<int, DemoFrame>, excerpt: string, score: int}>
     */
    public function retrieve(string $question, ?int $productId = null, int $limit = 3): Collection
    {
        $terms = $this->searchTerms($question);

        if ($terms->isEmpty()) {
            return collect();
        }

        $moments = DemoFeatureMoment::query()
            ->with('demoSession.product', 'product')
            ->where('approval_status', 'approved')
            ->when($productId, fn ($query) => $query->where(function ($nested) use ($productId) {
                $nested->where('product_id', $productId)
                    ->orWhereHas('demoSession', fn ($sessionQuery) => $sessionQuery->where('product_id', $productId));
            }))
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('feature_name', 'like', '%' . $term . '%')
                        ->orWhere('module_name', 'like', '%' . $term . '%')
                        ->orWhere('business_problem', 'like', '%' . $term . '%')
                        ->orWhere('summary', 'like', '%' . $term . '%');
                }
            })
            ->limit(200)
            ->get();

        return $moments
            ->map(fn (DemoFeatureMoment $moment) => $this->rankMoment($moment, $terms))
            ->filter(fn (array $match) => $match['score'] > 0)
            ->when($this->isComplianceQuestion($question), fn (Collection $matches) => $matches->filter(function (array $match) {
                $moment = $match['moment'];
                $text = Str::lower(Str::ascii(implode(' ', [
                    $moment->feature_name,
                    $moment->module_name,
                    $moment->business_problem,
                    $moment->summary,
                ])));

                return Str::contains($text, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit', 'inspection', 'lawsuit']);
            }))
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * @param Collection<int, string> $terms
     * @return array{moment: DemoFeatureMoment, frames: Collection<int, DemoFrame>, excerpt: string, score: int}
     */
    private function rankMoment(DemoFeatureMoment $moment, Collection $terms): array
    {
        $frameIds = collect($moment->frame_ids ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        $frames = $frameIds->isEmpty()
            ? collect()
            : DemoFrame::query()
                ->whereIn('id', $frameIds)
                ->whereIn('review_status', ['approved', 'unreviewed'])
                ->orderBy('timestamp_ms')
                ->limit(1)
                ->get();

        $text = Str::lower(Str::ascii(implode(' ', [
            $moment->feature_name,
            $moment->module_name,
            $moment->business_problem,
            $moment->summary,
            $frames->pluck('screen_summary')->filter()->implode(' '),
            $frames->pluck('cleaned_ui_text')->filter()->implode(' '),
            implode(' ', $moment->benefits ?? []),
            $moment->demoSession?->title,
            $moment->demoSession?->product?->name,
        ])));

        $score = 0;

        foreach ($terms as $term) {
            $term = Str::lower(Str::ascii($term));

            if (str_contains($text, $term)) {
                $score += strlen($term) > 7 ? 8 : 4;
            }
        }

        if ($terms->contains(fn (string $term) => str_contains($term, 'receivable'))) {
            $featureText = Str::lower(Str::ascii(implode(' ', [
                $moment->feature_name,
                $moment->module_name,
            ])));
            $momentText = Str::lower(Str::ascii(implode(' ', [
                $moment->feature_name,
                $moment->module_name,
                $moment->business_problem,
                $moment->summary,
            ])));

            if (str_contains($featureText, 'receivable')) {
                $score += 120;
            } elseif (str_contains($momentText, 'receivable')) {
                $score += 70;
            } else {
                $score -= 80;
            }
        }

        if ($terms->contains('compliance') || $terms->contains('delinquency') || $terms->contains('arrear') || $terms->contains('penalty') || $terms->contains('audit')) {
            $featureText = Str::lower(Str::ascii(implode(' ', [
                $moment->feature_name,
                $moment->module_name,
            ])));

            if (Str::contains($featureText, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit'])) {
                $score += 100;
            } elseif (! Str::contains($text, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit'])) {
                $score -= 80;
            }
        }

        if ($moment->summary) {
            $score += 3;
        }

        return [
            'moment' => $moment,
            'frames' => $frames,
            'excerpt' => $this->professionalCaption($moment),
            'score' => $score,
        ];
    }

    private function professionalCaption(DemoFeatureMoment $moment): string
    {
        $feature = $this->cleanCaptionText((string) $moment->feature_name);
        $module = $this->cleanCaptionText((string) $moment->module_name);
        $problem = $this->cleanCaptionText((string) $moment->business_problem);
        $title = $this->cleanCaptionText((string) $moment->demoSession?->title);

        $parts = [];

        if ($feature !== '') {
            $parts[] = $feature;
        } elseif ($module !== '') {
            $parts[] = $module;
        } elseif ($title !== '') {
            $parts[] = $title;
        } else {
            $parts[] = 'Relevant demo moment';
        }

        if ($problem !== '') {
            $parts[] = 'Business context: ' . $this->cleanCaptionText($problem);
        } elseif ($module !== '' && $feature !== $module) {
            $parts[] = 'Module context: ' . $module;
        }

        return Str::limit(implode('. ', array_map(fn (string $part) => rtrim($part, '.'), $parts)) . '.', 420);
    }

    private function cleanCaptionText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
        $text = preg_replace('/^(so|okay|ok|now|basically|you know|i mean)\s+/i', '', $text) ?? $text;
        $text = $this->normalizeProductAcronyms($text);
        $text = str_replace(['Receiveable', 'Receiveables'], ['Receivable', 'Receivables'], $text);

        return trim($text);
    }

    private function normalizeProductAcronyms(string $text): string
    {
        $text = preg_replace('/\bSsas\b/i', 'SSAS', $text) ?? $text;
        $text = preg_replace('/\bHrms\b/i', 'HRMS', $text) ?? $text;
        $text = preg_replace('/\bErms\b/i', 'ERMS', $text) ?? $text;
        $text = preg_replace('/\bEbpc\b/i', 'EBPC', $text) ?? $text;

        return $text;
    }

    /**
     * @return Collection<int, string>
     */
    private function searchTerms(string $question): Collection
    {
        $benefitMeansAdvantage = $this->benefitMeansAdvantage($question);
        $stopWords = collect([
            'about', 'after', 'also', 'does', 'from', 'have', 'into', 'that', 'their', 'there',
            'these', 'this', 'what', 'when', 'where', 'which', 'with', 'would', 'your', 'interact',
            'purpose', 'used', 'uses', 'using', 'system', 'module', 'product', 'please', 'explain', 'through',
            'use', 'key',
            'management',
            'comment', 'como', 'qual', 'hoe', 'pour', 'para', 'voor', 'avec', 'con', 'met',
        ]);

        return collect(preg_split('/[^a-z0-9]+/i', Str::lower(Str::ascii($question))))
            ->filter(fn (?string $term) => is_string($term) && strlen($term) >= 3)
            ->map(fn (string $term) => strlen($term) > 4 ? Str::singular($term) : $term)
            ->reject(fn (string $term) => $stopWords->contains($term))
            ->reject(fn (string $term) => $benefitMeansAdvantage && in_array($term, ['benefit', 'benefits'], true))
            ->unique()
            ->take(20)
            ->values();
    }

    private function benefitMeansAdvantage(string $question): bool
    {
        $normalized = Str::lower(Str::ascii(preg_replace('/\s+/', ' ', $question)));

        return (bool) preg_match('/\b(key\s+)?benefits?\s+(of|from|for)\b/', $normalized)
            || str_contains($normalized, 'advantages of');
    }

    private function isComplianceQuestion(string $question): bool
    {
        $normalized = Str::lower(Str::ascii($question));

        return Str::contains($normalized, ['compliance', 'delinquency', 'arrears', 'penalty', 'audit', 'inspection', 'lawsuit']);
    }
}
