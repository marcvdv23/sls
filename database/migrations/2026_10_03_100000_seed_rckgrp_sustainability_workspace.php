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

        $workspaceId = $this->workspaceId();
        $now = now();

        $this->seedSettings($workspaceId, $now);
        $this->seedProducts($workspaceId, $now);
        $this->seedReviewFocuses($workspaceId, $now);
        $this->seedSerpApiTemplates($workspaceId, $now);
        $this->seedKeywords($workspaceId, $now);
        $this->seedSources($workspaceId, $now);
        $this->seedCrawlerSettings($workspaceId, $now);
    }

    public function down(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $workspaceId = DB::table('workspaces')->where('workspace_key', 'sustainability_consulting')->value('id');

        if (! $workspaceId) {
            return;
        }

        foreach ([
            'crawler_settings',
            'intelligence_sources',
            'intelligence_keywords',
            'serpapi_search_templates',
            'review_focuses',
            'products',
            'sls_settings',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'workspace_id')) {
                DB::table($table)->where('workspace_id', $workspaceId)->delete();
            }
        }

        DB::table('workspaces')->where('id', $workspaceId)->delete();
    }

    private function workspaceId(): int
    {
        $now = now();

        DB::table('workspaces')->updateOrInsert(
            ['workspace_key' => 'sustainability_consulting'],
            [
                'entity_key' => 'rckgrp',
                'entity_name' => 'rckgrp',
                'name' => 'Sustainability Consulting',
                'description' => 'Sustainability consulting, ESG, climate, donor-funded environmental projects, and environmental policy intelligence.',
                'domain_label' => 'Sustainability, ESG, Climate, and Environmental Policy',
                'status' => 'active',
                'is_default' => false,
                'metadata' => json_encode(['source' => 'rckgrp_seed']),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return (int) DB::table('workspaces')->where('workspace_key', 'sustainability_consulting')->value('id');
    }

    private function seedSettings(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('sls_settings')) {
            return;
        }

        $settings = [
            ['platform.name', 'SLS', 'Platform', 'Platform name', 'Short product name shown in page titles and app chrome.'],
            ['platform.full_name', 'Sales', 'Platform', 'Platform full name', 'Plain-language product meaning. SLS is the generic sales platform.'],
            ['platform.legacy_name', 'SLS', 'Platform', 'Legacy name', 'Old or abbreviated app label used where existing users still expect it.'],
            ['entity.key', 'rckgrp', 'Entity', 'Entity key', 'Stable lowercase key for this organization or deployment.'],
            ['entity.name', 'rckgrp', 'Entity', 'Entity name', 'Legal or internal organization name for this SLS workspace.'],
            ['entity.display_name', 'rckgrp', 'Entity', 'Entity display name', 'User-facing organization name shown in navigation and login copy.'],
            ['workspace.key', 'sustainability_consulting', 'Workspace', 'Workspace key', 'Stable lowercase key for the current sales/intelligence workspace.'],
            ['workspace.name', 'Sustainability Consulting', 'Workspace', 'Workspace name', 'Name shown for the active sales/intelligence workspace.'],
            ['workspace.description', 'Sustainability consulting, ESG, climate, donor-funded environmental projects, and environmental policy intelligence.', 'Workspace', 'Workspace description', 'Short internal explanation of this workspace focus.', 'text'],
            ['workspace.domain_label', 'Sustainability, ESG, Climate, and Environmental Policy', 'Workspace', 'Domain label', 'Business domain label used in setup and intelligence screens.'],
            ['workspace.opportunity_label', 'Curated sustainability opportunities', 'Workspace', 'Opportunity label', 'Label for curated priority opportunities.'],
            ['workspace.review_label', 'Sustainability Review Desk', 'Workspace', 'Review queue label', 'Navigation label for the human review queue.'],
            ['products.default_code', 'SUST', 'Products', 'Default service code', 'Service code used as the default for mapping and intake.'],
            ['products.default_name', 'Sustainability Strategy Consulting', 'Products', 'Default service name', 'Service name used when finding the default product.'],
            ['products.order', 'SUST,ESG,ENVPOL,CLIMATE,CLIMFIN,CARBON,ENERGY,CIRCULAR,DONORENV', 'Products', 'Service order', 'Comma-separated service codes used for dashboard and selector ordering.'],
        ];

        foreach ($settings as $setting) {
            [$key, $value, $group, $label, $description] = $setting;
            $valueType = $setting[5] ?? 'string';

            DB::table('sls_settings')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'value_type' => $valueType,
                    'setting_group' => $group,
                    'label' => $label,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedProducts(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $products = [
            ['SUST', 'Sustainability Strategy Consulting', 'Strategy', 'Sustainability roadmaps, operating models, stakeholder plans, and implementation support.', true, 10],
            ['ESG', 'ESG Reporting and Advisory', 'ESG', 'ESG reporting, disclosure readiness, materiality, and governance support.', false, 20],
            ['ENVPOL', 'Environmental Policy Advisory', 'Policy', 'Environmental policy, regulation, and institutional advisory services.', false, 30],
            ['CLIMATE', 'Climate Adaptation and Resilience', 'Climate', 'Climate adaptation, resilience planning, vulnerability assessments, and program design.', false, 40],
            ['CLIMFIN', 'Climate and Green Finance Advisory', 'Finance', 'Climate finance, green finance, project pipeline, and donor funding support.', false, 50],
            ['CARBON', 'Carbon Markets and Emissions Reporting', 'Carbon', 'Carbon markets, emissions inventories, MRV, and reporting support.', false, 60],
            ['ENERGY', 'Renewable Energy Transition Advisory', 'Energy', 'Renewable energy transition, policy, and program advisory services.', false, 70],
            ['CIRCULAR', 'Circular Economy, Waste, and Water Advisory', 'Environment', 'Circular economy, waste, water, and resource-efficiency consulting.', false, 80],
            ['DONORENV', 'Donor-Funded Environmental Project Support', 'Donor projects', 'Business development and delivery support for donor-funded environmental projects.', false, 90],
        ];

        foreach ($products as [$code, $name, $category, $description, $isDefault, $sortOrder]) {
            DB::table('products')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'status' => 'active',
                    'category' => $category,
                    'sort_order' => $sortOrder,
                    'is_active' => true,
                    'is_default' => $isDefault,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedReviewFocuses(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('review_focuses')) {
            return;
        }

        $focuses = [
            ['consulting_opportunity', 'Consulting opportunity', 'Potential paid advisory, implementation, or technical-assistance work.', ['consulting', 'advisory', 'technical assistance', 'strategy', 'implementation support'], ['terms of reference', 'consulting services', 'request for proposals'], true, 10],
            ['policy_initiative', 'Policy initiative', 'Government or donor policy initiatives that may create consulting demand.', ['environmental policy', 'climate policy', 'sustainability policy', 'regulation', 'strategy'], ['national strategy', 'policy framework', 'action plan'], false, 20],
            ['donor_project', 'Donor-funded project', 'World Bank, UN, EU, development bank, and bilateral donor project activity.', ['world bank', 'afdb', 'adb', 'idb', 'undp', 'unep', 'giz', 'usaid', 'eu', 'green climate fund'], ['project procurement', 'project information document', 'grant', 'technical assistance'], false, 30],
            ['tender_rfp', 'Tender/RFP/RFI', 'Formal procurement notices, expressions of interest, RFPs, RFIs, and tenders.', ['tender', 'rfp', 'rfi', 'eoi', 'request for proposals', 'expression of interest', 'procurement notice'], ['invitation to bid', 'terms of reference', 'consulting firm'], false, 40],
            ['partnership_lead', 'Partnership lead', 'Potential partners, consortium members, implementers, or funders.', ['partnership', 'consortium', 'implementing partner', 'grant partner'], ['call for partners', 'strategic partnership'], false, 50],
            ['background_intelligence', 'Background intelligence', 'Market, policy, media, and institutional context worth tracking.', ['sustainability', 'esg', 'climate', 'environment', 'green economy'], ['new regulation', 'announced program', 'funding facility'], false, 60],
        ];

        foreach ($focuses as [$key, $label, $description, $terms, $signals, $isDefault, $sortOrder]) {
            DB::table('review_focuses')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'focus_key' => $key],
                [
                    'label' => $label,
                    'description' => $description,
                    'terms' => json_encode($terms),
                    'strong_signals' => json_encode($signals),
                    'metadata' => json_encode(['source' => 'rckgrp_seed']),
                    'sort_order' => $sortOrder,
                    'is_enabled' => true,
                    'is_default' => $isDefault,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedSerpApiTemplates(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('serpapi_search_templates')) {
            return;
        }

        $requiredTerms = ['tender', 'rfp', 'rfi', 'eoi', 'request for proposals', 'expression of interest', 'invitation to bid', 'procurement notice', 'terms of reference', 'consulting services', 'technical assistance'];
        $blockedDomains = ['capterra.', 'g2.com', 'softwareadvice.', 'linkedin.com', 'facebook.com', 'instagram.com', 'youtube.com', 'amazon.', 'coursera.', 'edx.', 'indeed.', 'glassdoor.'];
        $vendorTerms = ['pricing', 'free trial', 'book a demo', 'request a demo', 'features', 'reviews', 'best ', 'top ', 'course', 'training program'];

        $templates = [
            [
                'Sustainability consulting tenders',
                'tender_rfp',
                '"{country}" ({keywords}) ("request for proposals" OR RFP OR tender OR "expression of interest" OR EOI OR "terms of reference") -pricing -demo -"free trial"',
                ['sustainability consulting', 'ESG advisory', 'environmental consulting', 'climate adaptation consulting', 'climate resilience consulting', 'environmental policy advisory'],
            ],
            [
                'Climate and donor project opportunities',
                'donor_project',
                '"{country}" ({keywords}) ("technical assistance" OR "consulting services" OR procurement OR tender OR RFP OR EOI)',
                ['climate finance', 'green finance', 'climate adaptation', 'renewable energy transition', 'circular economy', 'waste management', 'water resource management'],
            ],
            [
                'Environmental policy initiatives',
                'policy_initiative',
                '"{country}" ({keywords}) ("policy" OR "strategy" OR "action plan" OR "program" OR "initiative")',
                ['environmental policy', 'sustainability policy', 'climate policy', 'national adaptation plan', 'green economy strategy', 'ESG regulation'],
            ],
        ];

        foreach ($templates as [$name, $focus, $query, $keywords]) {
            DB::table('serpapi_search_templates')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'name' => $name],
                [
                    'focus' => $focus,
                    'query_template' => $query,
                    'keywords' => json_encode($keywords),
                    'required_terms' => json_encode($requiredTerms),
                    'blocked_domains' => json_encode($blockedDomains),
                    'blocked_path_terms' => json_encode(['/careers/', '/jobs/', '/course/', '/courses/']),
                    'vendor_terms' => json_encode($vendorTerms),
                    'results_per_country' => 10,
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedKeywords(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('intelligence_keywords')) {
            return;
        }

        $keywords = [
            'consulting_opportunity' => ['sustainability consulting', 'ESG advisory', 'environmental consulting', 'climate consulting', 'technical assistance', 'institutional capacity building'],
            'policy_initiative' => ['environmental policy', 'climate policy', 'sustainability policy', 'green economy', 'national adaptation plan', 'climate resilience strategy'],
            'donor_project' => ['donor-funded project', 'World Bank climate', 'AfDB climate', 'Green Climate Fund', 'UNDP environment', 'EU green deal'],
            'tender_rfp' => ['tender', 'RFP', 'RFI', 'EOI', 'request for proposals', 'expression of interest', 'terms of reference'],
            'partnership_lead' => ['partnership', 'consortium', 'implementing partner', 'grant partner'],
            'background_intelligence' => ['sustainability', 'ESG', 'climate adaptation', 'climate finance', 'environmental regulation', 'carbon markets'],
        ];

        foreach ($keywords as $focus => $terms) {
            foreach ($terms as $term) {
                DB::table('intelligence_keywords')->updateOrInsert(
                    ['workspace_id' => $workspaceId, 'focus' => $focus, 'term' => $term, 'language_code' => 'en'],
                    [
                        'category' => 'rckgrp_seed',
                        'is_enabled' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    private function seedSources(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('intelligence_sources')) {
            return;
        }

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

    private function seedCrawlerSettings(int $workspaceId, mixed $now): void
    {
        if (! Schema::hasTable('crawler_settings')) {
            return;
        }

        $settings = [
            ['news_recent_publication_days', '45', 'integer', 'Recent publication window', 'Maximum age in days for sustainability news and policy intelligence.'],
            ['serpapi_results_per_country', '10', 'integer', 'SerpAPI results per country', 'Default result count for rckgrp sustainability searches.'],
            ['crawler_default_focus', 'consulting_opportunity', 'string', 'Default crawler focus', 'Default Review Desk focus used for sustainability crawler captures.'],
            ['scheduled_country_iso_scope', 'DZ,AO,BJ,BW,BF,BI,CV,CM,CF,TD,KM,CG,CI,CD,DJ,EG,GQ,ER,SZ,ET,GA,GM,GH,GN,GW,KE,LS,LR,LY,MG,MW,ML,MR,MU,MA,MZ,NA,NE,NG,RW,SN,SC,SL,SO,ZA,SS,ST,SD,TZ,TG,TN,UG,ZM,ZW,GB,NL', 'string', 'Scheduled country ISO scope', 'Countries covered by scheduled rckgrp sustainability crawlers.'],
            ['target_regions', 'Africa,Middle East,Asia,Latin America,Caribbean,Europe', 'string', 'Target regions', 'Default regions for rckgrp sustainability searches.'],
            ['target_languages', 'en,fr,pt,es,ar', 'string', 'Target languages', 'Default language groups for rckgrp sustainability searches.'],
        ];

        foreach ($settings as [$key, $value, $type, $label, $description]) {
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
};
