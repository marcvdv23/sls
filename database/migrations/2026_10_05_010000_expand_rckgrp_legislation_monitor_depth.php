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
                'key' => 'legislation_recent_publication_days',
                'value' => '120',
                'type' => 'integer',
                'label' => 'Legislation recent publication window',
                'description' => 'Maximum age in days for newly captured official legislation feed items.',
            ],
            [
                'key' => 'legislation_max_items',
                'value' => '100',
                'type' => 'integer',
                'label' => 'Legislation feed item limit',
                'description' => 'Maximum EUR-Lex Official Journal feed items to inspect per legislation monitor run.',
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

        $now = now();

        foreach ([
            'legislation_recent_publication_days' => '45',
            'legislation_max_items' => '25',
        ] as $key => $value) {
            DB::table('crawler_settings')
                ->where('workspace_id', $workspaceId)
                ->where('setting_key', $key)
                ->update([
                    'setting_value' => $value,
                    'updated_at' => $now,
                ]);
        }
    }
};
