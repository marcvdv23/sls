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

        foreach ([
            [
                'key' => 'tendersontime_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'label' => 'Enable TendersOnTime import',
                'description' => 'Runs the TendersOnTime API import into News & Tenders. Enable after TENDERSONTIME_USERNAME and TENDERSONTIME_KEY are configured on the server.',
            ],
            [
                'key' => 'tendersontime_endpoint_url',
                'value' => 'https://tmproject.tendersontime.org/tmpApi/tender-pull-json-2interact.php',
                'type' => 'url',
                'label' => 'TendersOnTime endpoint URL',
                'description' => 'REST endpoint used to pull daily tender notices.',
            ],
            [
                'key' => 'tendersontime_import_slots',
                'value' => '06:10',
                'type' => 'csv_times',
                'label' => 'TendersOnTime import run times',
                'description' => 'Daily run times for TendersOnTime synchronization. One previous-day pull is recommended during the trial limit.',
            ],
            [
                'key' => 'tendersontime_lookback_days',
                'value' => '1',
                'type' => 'integer',
                'label' => 'TendersOnTime posting-date lookback',
                'description' => 'Number of days before the run date to request. Use 1 for the recommended final previous-day pull.',
            ],
        ] as $setting) {
            DB::table('crawler_settings')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'setting_key' => $setting['key']],
                [
                    'setting_value' => $setting['value'],
                    'value_type' => $setting['type'],
                    'label' => $setting['label'],
                    'description' => $setting['description'],
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
            ->whereIn('setting_key', [
                'tendersontime_enabled',
                'tendersontime_endpoint_url',
                'tendersontime_import_slots',
                'tendersontime_lookback_days',
            ])
            ->delete();
    }
};
