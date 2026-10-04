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

        $now = now();

        DB::table('workspaces')
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'workspace_key'])
            ->each(function ($workspace) use ($now): void {
                $isSocialSecurity = (string) $workspace->workspace_key === 'social_security';

                DB::table('crawler_settings')->updateOrInsert(
                    [
                        'workspace_id' => $workspace->id,
                        'setting_key' => 'dashboard_monitor_stale_minutes',
                    ],
                    [
                        'setting_value' => $isSocialSecurity ? '30' : '1440',
                        'value_type' => 'integer',
                        'label' => 'Dashboard monitor stale threshold minutes',
                        'description' => 'Minutes without a completed run before dashboard monitor cards show Needs attention.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crawler_settings')) {
            return;
        }

        DB::table('crawler_settings')
            ->where('setting_key', 'dashboard_monitor_stale_minutes')
            ->delete();
    }
};
