<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'social_security')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        $now = now();

        DB::table('workspaces')
            ->where('id', $workspaceId)
            ->update([
                'name' => '2Interact',
                'description' => '2Interact sales intelligence across HRMS, SSAS, EBPC, and ERMS product lines, including social security, pensions, benefits, HR/payroll, risk, compliance, budgeting, and related tenders.',
                'domain_label' => '2Interact Public Sector Software',
                'updated_at' => $now,
            ]);

        if (Schema::hasTable('sls_settings')) {
            foreach ([
                'workspace.name' => ['2Interact', 'Workspace', 'Workspace name', 'Name shown for the active sales/intelligence workspace.', 'string'],
                'workspace.description' => ['2Interact sales intelligence across HRMS, SSAS, EBPC, and ERMS product lines, including social security, pensions, benefits, HR/payroll, risk, compliance, budgeting, and related tenders.', 'Workspace', 'Workspace description', 'Short internal explanation of this workspace focus.', 'text'],
                'workspace.domain_label' => ['2Interact Public Sector Software', 'Workspace', 'Domain label', 'Business domain label used in setup and intelligence screens.', 'string'],
                'workspace.opportunity_label' => ['Curated 2Interact opportunities', 'Workspace', 'Opportunity label', 'Label for curated priority opportunities.', 'string'],
                'workspace.intelligence_monitor_label' => ['2Interact Intelligence Monitor', 'Workspace', 'Intelligence monitor label', 'Dashboard label for the broad workspace intelligence monitor.', 'string'],
                'workspace.opportunity_monitor_label' => ['2Interact Tender/RFP Monitor', 'Workspace', 'Opportunity monitor label', 'Dashboard label for the workspace opportunity/tender monitor.', 'string'],
            ] as $key => [$value, $group, $label, $description, $type]) {
                DB::table('sls_settings')->updateOrInsert(
                    ['workspace_id' => $workspaceId, 'setting_key' => $key],
                    [
                        'setting_value' => $value,
                        'setting_group' => $group,
                        'label' => $label,
                        'description' => $description,
                        'value_type' => $type,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        if (Schema::hasTable('review_focuses')) {
            $this->updateFocus($workspaceId, 'social_security', '2Interact Intelligence', '2Interact workspace signals across the HRMS, SSAS, EBPC, and ERMS product lines, including social security, pensions, benefits, HR/payroll, risk, compliance, budgeting, learning, recruitment, and related tender updates.', $this->twoInteractTerms(), $this->twoInteractStrongSignals());
            $this->mergeFocusTerms($workspaceId, 'hrms_tenders', $this->hrmsTerms(), $this->hrmsStrongSignals());
            $this->mergeFocusTerms($workspaceId, 'erms_tenders', $this->riskTerms(), $this->riskStrongSignals());
            $this->mergeFocusTerms($workspaceId, 'ebpc_tenders', $this->budgetTerms(), $this->budgetStrongSignals());
        }

        if (Schema::hasTable('intelligence_keywords')) {
            $this->seedKeywords($workspaceId, 'social_security', $this->twoInteractTerms(), '2interact_core');
            $this->seedKeywords($workspaceId, 'hrms_tenders', $this->hrmsTerms(), '2interact_hr_hcm');
            $this->seedKeywords($workspaceId, 'erms_tenders', $this->riskTerms(), '2interact_risk_compliance');
            $this->seedKeywords($workspaceId, 'ebpc_tenders', $this->budgetTerms(), '2interact_budgeting');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'social_security')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        DB::table('workspaces')
            ->where('id', $workspaceId)
            ->update([
                'name' => 'Social Security Sales',
                'description' => 'Social security, pensions, public sector HR/payroll, and related sales intelligence.',
                'domain_label' => 'Social Security and Public Sector Software',
                'updated_at' => now(),
            ]);
    }

    private function updateFocus(int $workspaceId, string $focus, string $label, string $description, array $terms, array $strongSignals): void
    {
        $existing = DB::table('review_focuses')
            ->where('workspace_id', $workspaceId)
            ->where('focus_key', $focus)
            ->first();

        $mergedTerms = $this->mergeJsonArray($existing?->terms ?? null, $terms);
        $mergedSignals = $this->mergeJsonArray($existing?->strong_signals ?? null, $strongSignals);

        DB::table('review_focuses')->updateOrInsert(
            ['workspace_id' => $workspaceId, 'focus_key' => $focus],
            [
                'label' => $label,
                'description' => $description,
                'terms' => json_encode($mergedTerms),
                'strong_signals' => json_encode($mergedSignals),
                'is_enabled' => true,
                'is_default' => $focus === 'social_security',
                'sort_order' => $existing?->sort_order ?? 10,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ]
        );
    }

    private function mergeFocusTerms(int $workspaceId, string $focus, array $terms, array $strongSignals): void
    {
        $existing = DB::table('review_focuses')
            ->where('workspace_id', $workspaceId)
            ->where('focus_key', $focus)
            ->first();

        if (! $existing) {
            return;
        }

        DB::table('review_focuses')
            ->where('id', $existing->id)
            ->update([
                'terms' => json_encode($this->mergeJsonArray($existing->terms ?? null, $terms)),
                'strong_signals' => json_encode($this->mergeJsonArray($existing->strong_signals ?? null, $strongSignals)),
                'updated_at' => now(),
            ]);
    }

    private function seedKeywords(int $workspaceId, string $focus, array $terms, string $category): void
    {
        foreach ($terms as $term) {
            DB::table('intelligence_keywords')->updateOrInsert(
                [
                    'workspace_id' => $workspaceId,
                    'focus' => $focus,
                    'term' => $term,
                    'language_code' => 'en',
                ],
                [
                    'category' => $category,
                    'is_enabled' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function mergeJsonArray(?string $existingJson, array $newTerms): array
    {
        $existing = json_decode((string) $existingJson, true);
        $existing = is_array($existing) ? $existing : [];

        return collect($existing)
            ->merge($newTerms)
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique(fn (string $term) => strtolower($term))
            ->values()
            ->all();
    }

    private function twoInteractTerms(): array
    {
        return [
            '2interact',
            'ssas',
            'social security administration software',
            'social security administration system',
            'national provident fund',
            'national insurance',
            'social security',
            'social security administration',
            'social insurance',
            'benefits administration',
            'pension administration',
            'pension payroll',
            'pensioner payroll',
            'benefits management',
            'payroll management',
            'hris',
            'human resources information system',
            'erp',
            'enterprise resource planning',
            'enterprise resources planning',
            'compliance software',
            'risk management software',
            'budgeting software',
            'budgeting',
            'budget management',
            'hrms',
            'human resources management software',
            'hcm',
            'human capital management',
            'recruitment management software',
            'applicant tracking software',
            'talent management',
            'performance management',
            'competency management',
            'learning management',
            'training management',
            'succession planning',
            'career planning',
            'time and attendance',
            'leave management',
            'scheduling software',
        ];
    }

    private function twoInteractStrongSignals(): array
    {
        return [
            'national provident fund',
            'national insurance',
            'ssas',
            'social security administration software',
            'social security administration system',
            'social security',
            'social insurance',
            'benefits administration',
            'pension administration',
            'pension payroll',
            'pensioner payroll',
            'hris',
            'human resources information system',
            'hrms',
            'hcm',
            'human capital management',
            'risk management software',
            'compliance software',
            'budgeting software',
            'budget management',
        ];
    }

    private function hrmsTerms(): array
    {
        return [
            'hris',
            'human resources information system',
            'hrms',
            'human resources management software',
            'hcm',
            'human capital management',
            'payroll management',
            'pension payroll',
            'pensioner payroll',
            'benefits management',
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
    }

    private function hrmsStrongSignals(): array
    {
        return [
            'hris',
            'human resources information system',
            'human resources management software',
            'hcm',
            'human capital management',
            'payroll management',
            'pension payroll',
            'pensioner payroll',
            'benefits management',
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
        ];
    }

    private function riskTerms(): array
    {
        return ['compliance software', 'risk management software'];
    }

    private function riskStrongSignals(): array
    {
        return ['compliance software', 'risk management software'];
    }

    private function budgetTerms(): array
    {
        return ['budgeting software', 'budgeting', 'budget management'];
    }

    private function budgetStrongSignals(): array
    {
        return ['budgeting software', 'budgeting', 'budget management'];
    }
};
