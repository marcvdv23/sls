<?php

namespace App\Services;

use App\Models\CountryUpdate;
use App\Models\CountryUpdateOpportunity;
use App\Models\KnowledgeChunk;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CountryStoryOpportunityService
{
    public function __construct(
        private DemoMediaKnowledgeService $demoMediaKnowledge,
        private OpportunityEmailDraftService $emailDrafts,
    )
    {
    }

    public function createForSsas(CountryUpdate $update): CountryUpdateOpportunity
    {
        $product = Product::query()->where('name', 'Interact SSAS')->first();

        return $this->createForProduct($update, $product);
    }

    public function createForProduct(CountryUpdate $update, ?Product $product): CountryUpdateOpportunity
    {
        $issueArea = $this->issueArea($update);
        $query = $this->queryForIssue($issueArea, $update);
        $chunks = $this->knowledgeChunks($query, $product?->id);
        $visualMatches = $this->demoMediaKnowledge->retrieve($query, $product?->id, 3);

        $issueSummary = $this->issueSummary($update, $issueArea);
        $productAlignment = $this->productAlignment($issueArea, $chunks, $product);
        $fallbackEmail = $this->templateEmail($update, $issueArea, $productAlignment, $product);
        $suggestedEmail = $this->emailDrafts->draft($update, $issueArea, $issueSummary, $productAlignment, $chunks, $product, $fallbackEmail);

        return CountryUpdateOpportunity::query()->updateOrCreate(
            [
                'country_update_id' => $update->id,
                'product_id' => $product?->id,
            ],
            [
                'issue_area' => $issueArea,
                'opportunity_stage' => 'draft',
                'issue_summary' => $issueSummary,
                'product_alignment' => $productAlignment,
                'suggested_email' => $suggestedEmail,
                'knowledge_chunk_ids' => $chunks->pluck('id')->values()->all(),
                'demo_feature_moment_ids' => $visualMatches->pluck('moment.id')->filter()->values()->all(),
                'demo_frame_ids' => $visualMatches
                    ->flatMap(fn (array $match) => $match['frames']->pluck('id'))
                    ->values()
                    ->all(),
            ]
        );
    }

    private function issueArea(CountryUpdate $update): string
    {
        $text = Str::lower(Str::ascii($update->title . ' ' . $update->title_english . ' ' . $update->summary));

        $areas = [
            'Contribution collection and employer compliance' => ['contribution', 'employer', 'arrear', 'delinquen', 'compliance', 'collection', 'penalt'],
            'Benefit claims and benefit calculation' => ['benefit', 'claim', 'eligib', 'entitlement', 'award', 'medical referee', 'pension claim'],
            'Pension payments and beneficiary services' => ['payment', 'pensioner', 'beneficiary', 'allowance', 'disbursement', 'bank file'],
            'Digital transformation and self-service' => ['digital', 'online', 'portal', 'self-service', 'mobile', 'e-service', 'automation'],
            'Registration and identity management' => ['registration', 'register', 'identity', 'id card', 'social security number', 'member'],
            'Fraud, audit, and risk control' => ['fraud', 'audit', 'risk', 'investigation', 'control', 'inspection'],
            'Fund accounting and financial control' => ['accounting', 'ledger', 'general ledger', 'reconciliation', 'finance', 'fund'],
        ];

        foreach ($areas as $area => $signals) {
            if (collect($signals)->contains(fn (string $signal) => str_contains($text, $signal))) {
                return $area;
            }
        }

        return 'Social security administration modernization';
    }

    private function queryForIssue(string $issueArea, CountryUpdate $update): string
    {
        return trim($issueArea . ' ' . $update->country?->name . ' ' . $update->title_english . ' ' . $update->title);
    }

    /**
     * @return Collection<int, KnowledgeChunk>
     */
    private function knowledgeChunks(string $query, ?int $productId): Collection
    {
        $terms = collect(preg_split('/[^a-z0-9]+/i', Str::lower(Str::ascii($query))))
            ->filter(fn (?string $term) => is_string($term) && strlen($term) >= 5)
            ->reject(fn (string $term) => in_array($term, ['interact', 'social', 'security', 'administration', 'country'], true))
            ->unique()
            ->take(18)
            ->values();

        if ($terms->isEmpty()) {
            return collect();
        }

        return KnowledgeChunk::query()
            ->with('sourceDocument', 'product')
            ->where('approval_status', 'approved')
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->orWhere('chunk_title', 'like', '%' . $term . '%')
                        ->orWhere('chunk_text', 'like', '%' . $term . '%');
                }
            })
            ->limit(8)
            ->get();
    }

    private function issueSummary(CountryUpdate $update, string $issueArea): string
    {
        $country = $update->country?->name ?? 'the country';
        $title = $update->title_english ?: $update->title;
        $date = $update->publication_date?->toDateString() ?: 'an unknown publication date';

        return 'This intelligence item from ' . $country . ' was classified as "' . $issueArea . '". It was published on ' . $date . ' with the title "' . $title . '". The original source remains linked so the sales team can verify the context before outreach.';
    }

    /**
     * @param Collection<int, KnowledgeChunk> $chunks
     */
    private function productAlignment(string $issueArea, Collection $chunks, ?Product $product): string
    {
        $productName = $product?->name ?? 'the selected Interact product';
        $isSsas = $productName === 'Interact SSAS';

        $base = $isSsas
            ? match ($issueArea) {
                'Contribution collection and employer compliance' => 'Interact SSAS addresses this through configurable contribution rules, employer records, filing/payment workflows, arrears tracking, penalties, compliance activities, notices, and enforcement follow-up.',
                'Benefit claims and benefit calculation' => 'Interact SSAS addresses this through configurable benefit policies, eligibility rules, entitlement rules, formulas, claim workflows, approvals, award/rejection letters, payment processing, suspensions, and adjustments.',
                'Pension payments and beneficiary services' => 'Interact SSAS addresses this through beneficiary records, benefit payment processing, payment methods, trial/final payment controls, payment files, accounting entries, and self-service visibility.',
                'Digital transformation and self-service' => 'Interact SSAS addresses this through configurable workflows, online self-service, document handling, alerts, reporting, and integrated administration across registration, contributions, benefits, payments, and compliance.',
                'Registration and identity management' => 'Interact SSAS addresses this through registration workflows, social security number management, person and employer records, document capture, ID cards, relationship records, and lifecycle changes.',
                'Fraud, audit, and risk control' => 'Interact SSAS addresses this through compliance management, investigations, audit trails, workflow controls, configurable statuses/reasons, case tracking, reporting, and controlled adjustments.',
                'Fund accounting and financial control' => 'Interact SSAS addresses this through configurable accounting integration, GL entries, trial/final processing, reconciliations, payment controls, and reporting.',
                default => 'Interact SSAS addresses this through integrated social security administration capabilities across registration, contributions, benefits, payments, compliance, accounting, self-service, workflow, and reporting.',
            }
            : $productName . ' is the selected product for this intelligence item. Use the approved knowledge sources attached to this draft to shape the product-specific positioning and outreach.';

        $citations = $chunks
            ->map(fn (KnowledgeChunk $chunk) => $chunk->citation_label ?: $chunk->chunk_title)
            ->filter()
            ->unique()
            ->take(4)
            ->implode('; ');

        return $citations === '' ? $base : $base . ' Relevant approved knowledge sources: ' . $citations . '.';
    }

    private function templateEmail(CountryUpdate $update, string $issueArea, string $alignment, ?Product $product): string
    {
        $country = $update->country?->name ?? 'your market';
        $title = $update->title_english ?: $update->title;
        $productName = $product?->name ?? 'the selected Interact product';

        return implode("\n\n", [
            'Subject: Relevant ' . $productName . ' capabilities for ' . $issueArea,
            'Hello,',
            'I saw the recent item concerning ' . $country . ': "' . $title . '". It appears to relate to ' . Str::lower($issueArea) . ', which is an area where ' . $productName . ' may provide relevant operational support.',
            $alignment,
            'I would be happy to share a short example showing how ' . $productName . ' handles this type of issue in practice, including screenshots from the relevant workflow.',
            'Best regards,',
        ]);
    }
}
