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

        $countryCodes = $this->countryCodes();

        DB::table('intelligence_sources')
            ->where('workspace_id', $workspaceId)
            ->where('source_class', 'social_security_admin')
            ->delete();

        DB::table('intelligence_sources')
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('country_iso')
            ->whereNotIn(DB::raw('UPPER(country_iso)'), $countryCodes)
            ->delete();

        $now = now();

        foreach ($this->countryNames() as $iso => $countryName) {
            DB::table('intelligence_sources')->updateOrInsert(
                [
                    'workspace_id' => $workspaceId,
                    'country_iso' => $iso,
                    'name' => $countryName . ' environment, climate, and procurement watchlist',
                ],
                [
                    'region' => in_array($iso, ['GB', 'NL'], true) ? 'Europe' : 'Africa',
                    'domain' => null,
                    'url' => null,
                    'source_class' => 'government',
                    'procurement_portal_type' => 'national',
                    'focus' => 'sustainability',
                    'access_method' => 'country_keyword_monitor',
                    'connector' => 'country_intelligence_monitor',
                    'registration_status' => 'none',
                    'registration_notes' => 'Country-level rckgrp source target. Monitor searches for environment/climate ministries, policy announcements, procurement notices, donor projects, RFPs, RFIs, EOIs, and tenders for this country.',
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ($this->knownSources() as [$iso, $name, $domain, $url, $sourceClass, $portalType, $focus]) {
            DB::table('intelligence_sources')->updateOrInsert(
                [
                    'workspace_id' => $workspaceId,
                    'country_iso' => $iso,
                    'domain' => $domain,
                    'url' => $url,
                ],
                [
                    'region' => $iso ? (in_array($iso, ['GB', 'NL'], true) ? 'Europe' : 'Africa') : 'Global',
                    'name' => $name,
                    'source_class' => $sourceClass,
                    'procurement_portal_type' => $portalType,
                    'focus' => $focus,
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
        // Data cleanup/seed migration; leave source records in place.
    }

    /**
     * @return array<string, string>
     */
    private function countryNames(): array
    {
        return [
            'DZ' => 'Algeria', 'AO' => 'Angola', 'BJ' => 'Benin', 'BW' => 'Botswana',
            'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cabo Verde', 'CM' => 'Cameroon',
            'CF' => 'Central African Republic', 'TD' => 'Chad', 'KM' => 'Comoros', 'CG' => 'Congo',
            'CI' => "Cote d'Ivoire", 'CD' => 'Democratic Republic of the Congo', 'DJ' => 'Djibouti',
            'EG' => 'Egypt', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'SZ' => 'Eswatini',
            'ET' => 'Ethiopia', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GH' => 'Ghana',
            'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'KE' => 'Kenya', 'LS' => 'Lesotho',
            'LR' => 'Liberia', 'LY' => 'Libya', 'MG' => 'Madagascar', 'MW' => 'Malawi',
            'ML' => 'Mali', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MA' => 'Morocco',
            'MZ' => 'Mozambique', 'NA' => 'Namibia', 'NE' => 'Niger', 'NG' => 'Nigeria',
            'RW' => 'Rwanda', 'SN' => 'Senegal', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
            'SO' => 'Somalia', 'ZA' => 'South Africa', 'SS' => 'South Sudan',
            'ST' => 'Sao Tome and Principe', 'SD' => 'Sudan', 'TZ' => 'Tanzania',
            'TG' => 'Togo', 'TN' => 'Tunisia', 'UG' => 'Uganda', 'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe', 'GB' => 'United Kingdom', 'NL' => 'Netherlands',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function countryCodes(): array
    {
        return array_keys($this->countryNames());
    }

    /**
     * @return array<int, array{0:?string,1:string,2:string,3:string,4:string,5:string,6:string}>
     */
    private function knownSources(): array
    {
        return [
            [null, 'World Bank Projects & Procurement', 'worldbank.org', 'https://projects.worldbank.org/', 'donor_portal', 'project_pipeline', 'sustainability'],
            [null, 'African Development Bank Projects & Procurement', 'afdb.org', 'https://www.afdb.org/en/projects-and-operations/procurement', 'donor_portal', 'project_pipeline', 'sustainability'],
            [null, 'UNDP Procurement Notices', 'undp.org', 'https://procurement-notices.undp.org/', 'donor_portal', 'procurement_portal', 'sustainability'],
            [null, 'UNGM Procurement Notices', 'ungm.org', 'https://www.ungm.org/Public/Notice', 'donor_portal', 'procurement_portal', 'sustainability'],
            [null, 'Green Climate Fund Projects', 'greenclimate.fund', 'https://www.greenclimate.fund/projects', 'climate_finance_fund', 'project_pipeline', 'sustainability'],
            [null, 'Global Environment Facility Projects', 'thegef.org', 'https://www.thegef.org/projects-operations/projects', 'climate_finance_fund', 'project_pipeline', 'sustainability'],
            [null, 'NDC Partnership Knowledge Portal', 'ndcpartnership.org', 'https://ndcpartnership.org/knowledge-portal', 'policy_source', 'not_applicable', 'sustainability'],
            ['GB', 'UK Department for Environment, Food & Rural Affairs', 'gov.uk', 'https://www.gov.uk/government/organisations/department-for-environment-food-rural-affairs', 'government', 'not_applicable', 'sustainability'],
            ['GB', 'UK Contracts Finder', 'gov.uk', 'https://www.gov.uk/contracts-finder', 'central_tender_portal', 'national', 'tender_rfp'],
            ['GB', 'UK Find a Tender', 'find-tender.service.gov.uk', 'https://www.find-tender.service.gov.uk/', 'central_tender_portal', 'national', 'tender_rfp'],
            ['NL', 'Netherlands Climate Policy', 'government.nl', 'https://www.government.nl/topics/climate-change', 'government', 'not_applicable', 'sustainability'],
            ['NL', 'Netherlands Enterprise Agency', 'rvo.nl', 'https://english.rvo.nl/', 'government', 'not_applicable', 'sustainability'],
            ['NL', 'TenderNed', 'tenderned.nl', 'https://www.tenderned.nl/', 'central_tender_portal', 'national', 'tender_rfp'],
        ];
    }
};
