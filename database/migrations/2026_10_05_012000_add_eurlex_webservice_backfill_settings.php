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
                'key' => 'eurlex_backfill_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'label' => 'Enable EUR-Lex webservice delta monitor',
                'description' => 'Runs the registered EUR-Lex SOAP webservice monitor on a daily schedule. Enable after EURLEX_WEBSERVICE_USERNAME and EURLEX_WEBSERVICE_PASSWORD are configured on the server.',
            ],
            [
                'key' => 'eurlex_webservice_endpoint_url',
                'value' => 'https://eur-lex.europa.eu/EURLexWebService',
                'type' => 'url',
                'label' => 'EUR-Lex webservice endpoint URL',
                'description' => 'SOAP endpoint used by the EUR-Lex registered search webservice.',
            ],
            [
                'key' => 'eurlex_backfill_monitor_slots',
                'value' => '05:45',
                'type' => 'csv_times',
                'label' => 'EUR-Lex webservice monitor run times',
                'description' => 'Daily run times for ongoing EUR-Lex webservice delta checks.',
            ],
            [
                'key' => 'eurlex_backfill_delta_days',
                'value' => '14',
                'type' => 'integer',
                'label' => 'EUR-Lex delta lookback days',
                'description' => 'How many recent days the daily EUR-Lex webservice monitor checks for updates.',
            ],
            [
                'key' => 'eurlex_backfill_page_size',
                'value' => '25',
                'type' => 'integer',
                'label' => 'EUR-Lex backfill page size',
                'description' => 'Number of EUR-Lex webservice results requested per page. Keep small while testing credentials and query syntax.',
            ],
            [
                'key' => 'eurlex_backfill_max_pages_per_run',
                'value' => '2',
                'type' => 'integer',
                'label' => 'EUR-Lex max pages per run',
                'description' => 'Maximum webservice result pages requested per scheduled run, to avoid exhausting daily EUR-Lex call limits.',
            ],
            [
                'key' => 'eurlex_backfill_language',
                'value' => 'en',
                'type' => 'string',
                'label' => 'EUR-Lex search language',
                'description' => 'Search language sent to the EUR-Lex webservice.',
            ],
            [
                'key' => 'eurlex_backfill_query_terms',
                'value' => 'environment,climate,sustainability,emissions,carbon,energy,waste,water,biodiversity,circular economy,due diligence,esg,pollution,renewable,greenhouse',
                'type' => 'csv',
                'label' => 'EUR-Lex legislation query terms',
                'description' => 'Comma-separated sustainability terms used to build the default EUR-Lex expert query.',
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

        DB::table('crawler_settings')
            ->where('workspace_id', $workspaceId)
            ->whereIn('setting_key', [
                'eurlex_backfill_enabled',
                'eurlex_webservice_endpoint_url',
                'eurlex_backfill_monitor_slots',
                'eurlex_backfill_delta_days',
                'eurlex_backfill_page_size',
                'eurlex_backfill_max_pages_per_run',
                'eurlex_backfill_language',
                'eurlex_backfill_query_terms',
            ])
            ->delete();
    }
};
