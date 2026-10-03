<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('review_focuses')) {
            return;
        }

        $legacyFocusKeys = [
            'social_security',
            'hrms_tenders',
            'erms_tenders',
            'ebpc_tenders',
            'sector_tenders',
        ];

        $socialWorkspaceIds = DB::table('workspaces')
            ->where('workspace_key', 'social_security')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($socialWorkspaceIds === []) {
            return;
        }

        DB::table('review_focuses')
            ->whereIn('focus_key', $legacyFocusKeys)
            ->whereNotIn('workspace_id', $socialWorkspaceIds)
            ->delete();
    }

    public function down(): void
    {
        // Intentionally not restored. Non-social workspaces should define their own review focuses.
    }
};
