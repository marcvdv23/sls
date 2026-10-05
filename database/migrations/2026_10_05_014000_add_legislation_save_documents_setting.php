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
            ['workspace_id' => $workspaceId, 'setting_key' => 'legislation_save_documents'],
            [
                'setting_value' => 'false',
                'value_type' => 'boolean',
                'label' => 'Auto-save legislation documents',
                'description' => 'When disabled, legislation crawlers create Review Desk items with official source links only. Enable this only when crawlers should retrieve, archive, and index the full legal documents automatically.',
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
            ->where('setting_key', 'legislation_save_documents')
            ->delete();
    }
};
