<?php

namespace App\Services;

use Illuminate\Support\Str;

class KnowledgeChunkClassifier
{
    /**
     * @return array{content_type: string, business_area: string, answer_priority: int}
     */
    public function classify(string $title, string $text, ?string $sourceType = null): array
    {
        $haystack = Str::lower(Str::ascii($title . ' ' . $text));

        $contentType = $this->contentType($haystack, $sourceType);
        $businessArea = $this->businessArea($haystack);

        return [
            'content_type' => $contentType,
            'business_area' => $businessArea,
            'answer_priority' => $this->answerPriority($contentType),
        ];
    }

    private function contentType(string $text, ?string $sourceType): string
    {
        if (in_array($sourceType, ['webinar_transcript', 'product_demo_transcript'], true)) {
            return 'transcript_explanation';
        }

        if (str_contains($text, 'term definitions') || str_contains($text, 'table of contents and glossary')) {
            return 'glossary_definition';
        }

        if (Str::contains($text, ['general setup', 'policy', 'policies', 'rate', 'rates', 'formula', 'formulas', 'rule', 'rules', 'parameter', 'parameters', 'effective date', 'ceiling', 'employee group', 'numbering', 'template'])) {
            return 'configuration';
        }

        if (str_contains($text, 'form usage')) {
            return 'screen_purpose';
        }

        if (Str::contains($text, ['calculated', 'calculation', 'automatically', 'determines', 'based on', 'apply', 'applies', 'eligible', 'eligibility', 'exemption', 'threshold'])) {
            return 'business_logic';
        }

        if (Str::contains($text, ['trial posting', 'final posting', 'process', 'processed', 'generate', 'generated', 'approve', 'approval', 'post ', 'posting', 'request'])) {
            return 'transaction_processing';
        }

        if (Str::contains($text, ['created', 'liability', 'due', 'payment', 'notice', 'letter', 'gl ', 'status', 'suspended', 'report', 'register'])) {
            return 'workflow_outcome';
        }

        if (preg_match('/\b1\.\s+.+\b2\.\s+/s', $text) || Str::contains($text, ['click ', 'select ', 'go to ', 'button'])) {
            return 'procedure_steps';
        }

        return 'business_logic';
    }

    private function businessArea(string $text): string
    {
        $areas = [
            'contributions' => ['contribution', 'contributions', 'filing', 'earnings', 'employee group', 'employer due'],
            'benefits' => ['benefit', 'claim', 'claimant', 'award', 'pension', 'disability', 'medical referee'],
            'registration' => ['registration', 'registered', 'ssn', 'social security number', 'employer registration', 'individual registration'],
            'payments' => ['payment', 'payments', 'receipt', 'bank file', 'cheque', 'check', 'payable'],
            'compliance' => ['compliance', 'audit', 'inspection', 'delinquency', 'penalty', 'arrears'],
            'general_setup' => ['general setup', 'policy', 'configuration', 'parameter', 'setup'],
            'documents_letters' => ['letter', 'template', 'certificate', 'document', 'notice'],
            'financials' => ['general ledger', 'gl ', 'receivable', 'payable', 'invoice'],
        ];

        foreach ($areas as $area => $signals) {
            if (Str::contains($text, $signals)) {
                return $area;
            }
        }

        return 'general';
    }

    private function answerPriority(string $contentType): int
    {
        return match ($contentType) {
            'configuration' => 95,
            'business_logic' => 90,
            'glossary_definition' => 85,
            'screen_purpose' => 80,
            'workflow_outcome' => 70,
            'transaction_processing' => 60,
            'transcript_explanation' => 55,
            'procedure_steps' => 35,
            default => 50,
        };
    }
}
