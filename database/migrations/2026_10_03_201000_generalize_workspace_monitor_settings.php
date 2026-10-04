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

        DB::table('workspaces')
            ->where('workspace_key', '<>', 'social_security')
            ->orderBy('id')
            ->get(['id', 'workspace_key'])
            ->each(function ($workspace) use ($now) {
                DB::table('sls_settings')->updateOrInsert(
                    ['workspace_id' => $workspace->id, 'setting_key' => 'workspace.legacy_focus_keys'],
                    [
                        'setting_value' => '',
                        'value_type' => 'text',
                        'setting_group' => 'Workspace Sources',
                        'label' => 'Legacy tender focus keys',
                        'description' => 'Comma-separated legacy focus keys that should still accept generic tender sources.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });

        $rckgrpWorkspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $rckgrpWorkspaceId) {
            return;
        }

        $settings = [
            'workspace.intelligence_monitor_label' => [
                'Sustainability and Climate Intelligence',
                'string',
                'Workspace',
                'Intelligence monitor label',
                'Dashboard label for the broad workspace intelligence monitor.',
            ],
            'workspace.opportunity_monitor_label' => [
                'Sustainability Tender/RFP Monitor',
                'string',
                'Workspace',
                'Opportunity monitor label',
                'Dashboard label for the workspace opportunity/tender monitor.',
            ],
            'workspace.monitoring_focuses' => [
                implode("\n", [
                    'Donor and development-bank project pipelines',
                    'RFP, RFI, EOI, tender, and procurement portals',
                    'Environmental, climate, and sustainability policy announcements',
                    'Climate finance funds, facilities, and grant windows',
                    'ESG regulation, carbon markets, and national climate plans',
                    'Relevant sustainability media and implementing-partner signals',
                ]),
                'text',
                'Workspace',
                'Monitoring focus descriptions',
                'One dashboard source-coverage focus per line.',
            ],
        ];

        foreach ($settings as $key => [$value, $type, $group, $label, $description]) {
            DB::table('sls_settings')->updateOrInsert(
                ['workspace_id' => $rckgrpWorkspaceId, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'value_type' => $type,
                    'setting_group' => $group,
                    'label' => $label,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('sls_settings')) {
            return;
        }

        $rckgrpWorkspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if ($rckgrpWorkspaceId) {
            DB::table('sls_settings')
                ->where('workspace_id', $rckgrpWorkspaceId)
                ->whereIn('setting_key', [
                    'workspace.intelligence_monitor_label',
                    'workspace.opportunity_monitor_label',
                    'workspace.monitoring_focuses',
                ])
                ->delete();
        }
    }
};
