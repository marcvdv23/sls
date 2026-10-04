<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('crawler_settings')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        $terms = $this->terms();

        DB::table('crawler_settings')->updateOrInsert(
            [
                'workspace_id' => $workspaceId,
                'setting_key' => 'workspace_required_relevance_terms',
            ],
            [
                'setting_value' => implode("\n", $terms),
                'value_type' => 'text',
                'label' => 'Workspace required relevance terms',
                'description' => 'Domain terms required before non-legacy workspace crawlers keep a captured item.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if (! Schema::hasTable('country_updates')) {
            return;
        }

        $pattern = collect($terms)
            ->map(fn (string $term) => preg_quote(strtolower($term), '/'))
            ->implode('|');

        DB::table('country_updates')
            ->where('workspace_id', $workspaceId)
            ->where('review_status', 'unreviewed')
            ->where(function ($query) {
                $query->whereNull('is_favorite')->orWhere('is_favorite', false);
            })
            ->whereNull('map_processed_at')
            ->whereRaw("LOWER(CONCAT_WS(' ', title, title_english, title_original, summary, summary_english, source_name, source_url)) NOT REGEXP ?", [$pattern])
            ->update([
                'review_status' => 'rejected',
                'rejection_reason_code' => 'not_relevant',
                'rejection_reason' => 'Auto-dropped because it does not match the workspace required relevance terms.',
                'rejected_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('crawler_settings')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        DB::table('crawler_settings')
            ->where('workspace_id', $workspaceId)
            ->where('setting_key', 'workspace_required_relevance_terms')
            ->delete();
    }

    /**
     * @return array<int, string>
     */
    private function terms(): array
    {
        return [
            'sustainability',
            'sustainable',
            'climate',
            'environment',
            'environmental',
            'esg',
            'carbon',
            'emissions',
            'decarbonization',
            'decarbonisation',
            'renewable energy',
            'energy transition',
            'green economy',
            'green finance',
            'climate finance',
            'adaptation',
            'mitigation',
            'resilience',
            'biodiversity',
            'circular economy',
            'waste management',
            'water resource',
            'pollution',
            'conservation',
        ];
    }
};
