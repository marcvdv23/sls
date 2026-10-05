<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('intelligence_sources')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        $now = now();
        $sources = [
            [
                'name' => 'EUR-Lex Parliament and Council Legislation',
                'url' => 'https://eur-lex.europa.eu/EN/display-feed.rss?rssId=162',
                'connector' => 'eurlex_rss',
                'notes' => 'Official EUR-Lex RSS feed for legislative acts adopted by the Council or jointly by the Council and European Parliament.',
            ],
            [
                'name' => 'EUR-Lex Commission Proposals',
                'url' => 'https://eur-lex.europa.eu/EN/display-feed.rss?rssId=161',
                'connector' => 'eurlex_rss',
                'notes' => 'Official EUR-Lex RSS feed for Commission proposals and related documents. Used as early-stage legislative monitoring, then reviewed under the legislation focus.',
            ],
        ];

        foreach ($sources as $source) {
            DB::table('intelligence_sources')->updateOrInsert(
                [
                    'workspace_id' => $workspaceId,
                    'domain' => 'eur-lex.europa.eu',
                    'url' => $source['url'],
                ],
                [
                    'country_iso' => 'EU',
                    'region' => 'Europe',
                    'name' => $source['name'],
                    'source_class' => 'legislation_source',
                    'procurement_portal_type' => 'not_applicable',
                    'focus' => 'legislation',
                    'access_method' => 'rss',
                    'connector' => $source['connector'],
                    'registration_status' => 'none',
                    'registration_notes' => $source['notes'],
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('intelligence_sources')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        DB::table('intelligence_sources')
            ->where('workspace_id', $workspaceId)
            ->whereIn('url', [
                'https://eur-lex.europa.eu/EN/display-feed.rss?rssId=162',
                'https://eur-lex.europa.eu/EN/display-feed.rss?rssId=161',
            ])
            ->delete();
    }
};
