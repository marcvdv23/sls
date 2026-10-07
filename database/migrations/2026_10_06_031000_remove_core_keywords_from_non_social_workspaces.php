<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('intelligence_keywords')) {
            return;
        }

        $socialWorkspaceId = DB::table('workspaces')
            ->where('workspace_key', 'social_security')
            ->value('id');

        if (! $socialWorkspaceId) {
            return;
        }

        DB::table('intelligence_keywords')
            ->where('workspace_id', '<>', (int) $socialWorkspaceId)
            ->where('category', 'core')
            ->delete();
    }

    public function down(): void
    {
        // Core keyword rows outside the social-security workspace were legacy seed leakage.
    }
};
