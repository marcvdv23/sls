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
            ['World Bank Projects & Procurement', 'worldbank.org', 'https://projects.worldbank.org/', 'donor_portal', 'project_pipeline'],
            ['African Development Bank Projects & Procurement', 'afdb.org', 'https://www.afdb.org/en/projects-and-operations/procurement', 'donor_portal', 'project_pipeline'],
            ['Asian Development Bank Projects & Tenders', 'adb.org', 'https://www.adb.org/projects/tenders', 'donor_portal', 'procurement_portal'],
            ['Inter-American Development Bank Projects', 'iadb.org', 'https://www.iadb.org/en/projects', 'donor_portal', 'project_pipeline'],
            ['UNDP Procurement Notices', 'undp.org', 'https://procurement-notices.undp.org/', 'donor_portal', 'procurement_portal'],
            ['UNGM Procurement Notices', 'ungm.org', 'https://www.ungm.org/Public/Notice', 'donor_portal', 'procurement_portal'],
            ['Green Climate Fund Projects', 'greenclimate.fund', 'https://www.greenclimate.fund/projects', 'donor_portal', 'project_pipeline'],
            ['EU Funding & Tenders', 'ec.europa.eu', 'https://ec.europa.eu/info/funding-tenders/opportunities/portal/screen/home', 'donor_portal', 'procurement_portal'],
        ];

        foreach ($sources as [$name, $domain, $url, $sourceClass, $portalType]) {
            DB::table('intelligence_sources')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'domain' => $domain, 'url' => $url],
                [
                    'country_iso' => null,
                    'region' => 'Global',
                    'name' => $name,
                    'source_class' => $sourceClass,
                    'procurement_portal_type' => $portalType,
                    'focus' => 'sustainability',
                    'access_method' => 'web',
                    'connector' => null,
                    'registration_status' => 'none',
                    'registration_notes' => null,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        // Seed-only migration; leave configured source records in place.
    }
};
