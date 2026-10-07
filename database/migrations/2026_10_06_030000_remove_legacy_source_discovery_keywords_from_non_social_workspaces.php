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
            ->where('focus', 'source_discovery')
            ->where('workspace_id', '<>', (int) $socialWorkspaceId)
            ->where(function ($query) {
                $query
                    ->whereNull('category')
                    ->orWhereIn('category', [
                        'core',
                        'civil_service',
                        'labour_ministry',
                        'procurement_portal',
                        'social_security_admin',
                    ]);
            })
            ->delete();
    }

    public function down(): void
    {
        // Non-social workspaces should not receive the legacy social-security source discovery seed terms.
    }
};
