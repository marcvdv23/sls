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

        DB::table('crawler_settings')->updateOrInsert(
            [
                'workspace_id' => $workspaceId,
                'setting_key' => 'scheduled_country_iso_scope',
            ],
            [
                'setting_value' => implode(',', $this->countryCodes()),
                'value_type' => 'string',
                'label' => 'Scheduled country ISO scope',
                'description' => 'Countries covered by scheduled rckgrp sustainability crawlers.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('crawler_settings')->updateOrInsert(
            [
                'workspace_id' => $workspaceId,
                'setting_key' => 'target_regions',
            ],
            [
                'setting_value' => 'Africa,United Kingdom,Netherlands',
                'value_type' => 'string',
                'label' => 'Target regions',
                'description' => 'Default regions for rckgrp sustainability searches.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
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
            ->where('setting_key', 'scheduled_country_iso_scope')
            ->delete();
    }

    /**
     * @return array<int, string>
     */
    private function countryCodes(): array
    {
        return [
            'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD',
            'KM', 'CG', 'CI', 'CD', 'DJ', 'EG', 'GQ', 'ER', 'SZ', 'ET',
            'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG',
            'MW', 'ML', 'MR', 'MU', 'MA', 'MZ', 'NA', 'NE', 'NG', 'RW',
            'SN', 'SC', 'SL', 'SO', 'ZA', 'SS', 'ST', 'SD', 'TZ', 'TG',
            'TN', 'UG', 'ZM', 'ZW', 'GB', 'NL',
        ];
    }
};
