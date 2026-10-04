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

        $now = now();
        $settings = [
            'workspace_monitor_slots' => [
                '00:00,02:00,04:00,06:00,08:00,10:00,12:00,14:00,16:00,18:00,20:00,22:00',
                'csv_times',
                'Workspace monitor run times',
                'Comma-separated HH:MM times for scheduled workspace crawlers. Each focus is staggered from each slot.',
            ],
            'workspace_monitor_batch_size' => [
                '8',
                'integer',
                'Workspace monitor countries per run',
                'Number of countries checked per scheduled workspace focus run.',
            ],
            'scheduled_max_results' => [
                '10',
                'integer',
                'Scheduled max results per country',
                'Maximum candidate items kept for each scheduled country/focus run.',
            ],
        ];

        foreach ($settings as $key => [$value, $type, $label, $description]) {
            DB::table('crawler_settings')->updateOrInsert(
                [
                    'workspace_id' => $workspaceId,
                    'setting_key' => $key,
                ],
                [
                    'setting_value' => $value,
                    'value_type' => $type,
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
            ->whereIn('setting_key', ['workspace_monitor_slots', 'workspace_monitor_batch_size'])
            ->delete();
    }
};
