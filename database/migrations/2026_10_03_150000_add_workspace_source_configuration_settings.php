<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('sls_settings')) {
            return;
        }

        $now = now();
        $defaultLegacyFocuses = 'social_security,hrms_tenders,erms_tenders,ebpc_tenders,sector_tenders';
        $sustainabilitySourceClasses = implode(',', [
            'donor_portal',
            'donor_tender_portal',
            'project_pipeline',
            'procurement_portal',
            'climate_finance_fund',
            'policy_source',
            'government',
            'central_tender_portal',
            'local_media',
            'news_aggregator',
        ]);

        $genericSourceClasses = implode(',', [
            'donor_portal',
            'donor_tender_portal',
            'project_pipeline',
            'procurement_portal',
            'government',
            'central_tender_portal',
            'local_media',
            'news_aggregator',
        ]);

        DB::table('workspaces')
            ->orderBy('id')
            ->get(['id', 'workspace_key'])
            ->each(function ($workspace) use ($now, $defaultLegacyFocuses, $sustainabilitySourceClasses, $genericSourceClasses) {
                $workspaceKey = (string) $workspace->workspace_key;

                $settings = match ($workspaceKey) {
                    'social_security' => [
                        'workspace.source_classes' => '',
                        'workspace.excluded_source_classes' => '',
                        'workspace.source_focus_aliases' => '',
                        'workspace.legacy_focus_keys' => $defaultLegacyFocuses,
                    ],
                    'sustainability_consulting' => [
                        'workspace.source_classes' => $sustainabilitySourceClasses,
                        'workspace.excluded_source_classes' => 'social_security_admin',
                        'workspace.source_focus_aliases' => 'sustainability,climate,environment,environmental,esg',
                        'workspace.legacy_focus_keys' => $defaultLegacyFocuses,
                    ],
                    default => [
                        'workspace.source_classes' => $genericSourceClasses,
                        'workspace.excluded_source_classes' => 'social_security_admin',
                        'workspace.source_focus_aliases' => str_replace('_', ',', $workspaceKey),
                        'workspace.legacy_focus_keys' => $defaultLegacyFocuses,
                    ],
                };

                foreach ($settings as $key => $value) {
                    DB::table('sls_settings')->updateOrInsert(
                        ['workspace_id' => $workspace->id, 'setting_key' => $key],
                        [
                            'setting_value' => $value,
                            'value_type' => 'text',
                            'setting_group' => 'Workspace Sources',
                            'label' => $this->labelFor($key),
                            'description' => $this->descriptionFor($key),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sls_settings')) {
            return;
        }

        DB::table('sls_settings')
            ->whereIn('setting_key', [
                'workspace.source_classes',
                'workspace.excluded_source_classes',
                'workspace.source_focus_aliases',
                'workspace.legacy_focus_keys',
            ])
            ->delete();
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            'workspace.source_classes' => 'Included source classes',
            'workspace.excluded_source_classes' => 'Excluded source classes',
            'workspace.source_focus_aliases' => 'Source focus aliases',
            'workspace.legacy_focus_keys' => 'Legacy tender focus keys',
            default => $key,
        };
    }

    private function descriptionFor(string $key): string
    {
        return match ($key) {
            'workspace.source_classes' => 'Comma-separated source classes shown and monitored for this workspace. Leave blank to allow all enabled source classes.',
            'workspace.excluded_source_classes' => 'Comma-separated source classes that should never appear for this workspace.',
            'workspace.source_focus_aliases' => 'Comma-separated generic source focus values that can feed this workspace when they do not exactly match a review focus.',
            'workspace.legacy_focus_keys' => 'Comma-separated legacy focus keys that should still accept generic tender sources.',
            default => '',
        };
    }
};
