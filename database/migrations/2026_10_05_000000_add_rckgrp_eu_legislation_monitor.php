<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        $now = now();

        if (Schema::hasTable('review_focuses')) {
            DB::table('review_focuses')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'focus_key' => 'legislation'],
                [
                    'label' => 'Legislation',
                    'description' => 'Enacted laws, regulations, directives, decisions, and final Official Journal legislation relevant to sustainability, climate, ESG, and environmental policy.',
                    'terms' => json_encode([
                        'legislation',
                        'regulation',
                        'directive',
                        'decision',
                        'official journal',
                        'environment',
                        'climate',
                        'sustainability',
                        'emissions',
                        'carbon',
                        'energy',
                        'waste',
                        'water',
                        'biodiversity',
                        'circular economy',
                        'due diligence',
                        'esg',
                    ]),
                    'strong_signals' => json_encode([
                        'CELEX',
                        'EUR-Lex',
                        'Official Journal of the European Union',
                        'Regulation (EU)',
                        'Directive (EU)',
                        'Decision (EU)',
                    ]),
                    'metadata' => json_encode([
                        'source' => 'rckgrp_eu_legislation_seed',
                        'item_type' => 'law',
                        'official_artifact' => 'pdf',
                        'monitor_driver' => 'eu_legislation',
                    ]),
                    'sort_order' => 70,
                    'is_enabled' => true,
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (Schema::hasTable('intelligence_sources')) {
            DB::table('intelligence_sources')->updateOrInsert(
                [
                    'workspace_id' => $workspaceId,
                    'domain' => 'eur-lex.europa.eu',
                    'url' => 'https://eur-lex.europa.eu/EN/display-feed.rss?rssId=165',
                ],
                [
                    'country_iso' => 'EU',
                    'region' => 'Europe',
                    'name' => 'EUR-Lex Official Journal L',
                    'source_class' => 'legislation_source',
                    'procurement_portal_type' => 'not_applicable',
                    'focus' => 'legislation',
                    'access_method' => 'rss',
                    'connector' => 'eurlex_official_journal_l',
                    'registration_status' => 'none',
                    'registration_notes' => 'Official EUR-Lex RSS feed for Acts of the Official Journal L. SLS retrieves CELEX HTML/XML/PDF links from each feed item.',
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (Schema::hasTable('crawler_settings')) {
            foreach ([
                ['legislation_monitor_slots', '05:20', 'csv_times', 'Legislation monitor run times', 'Daily run times for workspace legislation monitors.'],
                ['legislation_recent_publication_days', '45', 'integer', 'Legislation recent publication window', 'Maximum age in days for newly captured legislation feed items.'],
                ['legislation_max_items', '25', 'integer', 'Legislation feed item limit', 'Maximum legislation feed items to inspect per run.'],
                ['legislation_include_pdf', 'true', 'boolean', 'Store official legislation PDFs', 'When enabled, SLS archives the official EUR-Lex PDF when available and indexes the HTML text for search.'],
            ] as [$key, $value, $type, $label, $description]) {
                DB::table('crawler_settings')->updateOrInsert(
                    ['workspace_id' => $workspaceId, 'setting_key' => $key],
                    [
                        'setting_value' => $value,
                        'value_type' => $type,
                        'label' => $label,
                        'description' => $description,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        if (Schema::hasTable('sls_settings')) {
            $existingClasses = (string) DB::table('sls_settings')
                ->where('workspace_id', $workspaceId)
                ->where('setting_key', 'workspace.source_classes')
                ->value('setting_value');

            $classes = collect(explode(',', $existingClasses))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->push('legislation_source')
                ->unique()
                ->implode(',');

            DB::table('sls_settings')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'setting_key' => 'workspace.source_classes'],
                [
                    'setting_value' => $classes,
                    'value_type' => 'text',
                    'setting_group' => 'Workspace Sources',
                    'label' => 'Included source classes',
                    'description' => 'Comma-separated source classes shown and monitored for this workspace. Leave blank to allow all enabled source classes.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $workspaceId = DB::table('workspaces')
            ->where('workspace_key', 'sustainability_consulting')
            ->value('id');

        if (! $workspaceId) {
            return;
        }

        if (Schema::hasTable('review_focuses')) {
            DB::table('review_focuses')
                ->where('workspace_id', $workspaceId)
                ->where('focus_key', 'legislation')
                ->delete();
        }

        if (Schema::hasTable('intelligence_sources')) {
            DB::table('intelligence_sources')
                ->where('workspace_id', $workspaceId)
                ->where('connector', 'eurlex_official_journal_l')
                ->delete();
        }

        if (Schema::hasTable('crawler_settings')) {
            DB::table('crawler_settings')
                ->where('workspace_id', $workspaceId)
                ->whereIn('setting_key', [
                    'legislation_monitor_slots',
                    'legislation_recent_publication_days',
                    'legislation_max_items',
                    'legislation_include_pdf',
                ])
                ->delete();
        }
    }
};
