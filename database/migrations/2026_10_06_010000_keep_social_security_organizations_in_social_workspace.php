<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('market_organizations')) {
            return;
        }

        $socialWorkspaceId = DB::table('workspaces')
            ->where('workspace_key', 'social_security')
            ->value('id');

        if (! $socialWorkspaceId || ! Schema::hasColumn('market_organizations', 'workspace_id')) {
            return;
        }

        DB::table('market_organizations')
            ->where('workspace_id', '<>', (int) $socialWorkspaceId)
            ->where(function ($query) {
                $query
                    ->whereIn('lead_source', [
                        'country_social_security_directory',
                        'managed_social_security_source',
                    ])
                    ->orWhere('source_fingerprint', 'like', 'social-security-admin:%')
                    ->orWhere('source_fingerprint', 'like', 'social-security-source:%');
            })
            ->delete();

        if (! Schema::hasTable('market_crawlers') || ! Schema::hasColumn('market_crawlers', 'workspace_id')) {
            return;
        }

        DB::table('market_crawlers')
            ->where('workspace_id', '<>', (int) $socialWorkspaceId)
            ->where('crawler_key', 'social_security_organization')
            ->delete();
    }

    public function down(): void
    {
        // This migration removes accidentally seeded workspace pollution only.
        // The removed records are regenerated in the social-security workspace by the normal crawler seeder.
    }
};
