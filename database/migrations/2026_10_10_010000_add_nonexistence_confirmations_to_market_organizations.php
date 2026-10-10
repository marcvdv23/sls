<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('market_organizations')) {
            return;
        }

        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'organization_nonexistent_confirmed_at')) {
                $table->timestamp('organization_nonexistent_confirmed_at')->nullable()->after('organization_subcategory')->index();
            }

            if (! Schema::hasColumn('market_organizations', 'website_url_nonexistent_confirmed_at')) {
                $table->timestamp('website_url_nonexistent_confirmed_at')->nullable()->after('website_domain');
            }

            if (! Schema::hasColumn('market_organizations', 'news_page_url_nonexistent_confirmed_at')) {
                $table->timestamp('news_page_url_nonexistent_confirmed_at')->nullable()->after('news_page_url');
            }

            if (! Schema::hasColumn('market_organizations', 'procurement_page_url_nonexistent_confirmed_at')) {
                $table->timestamp('procurement_page_url_nonexistent_confirmed_at')->nullable()->after('procurement_page_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('market_organizations')) {
            return;
        }

        Schema::table('market_organizations', function (Blueprint $table) {
            foreach ([
                'organization_nonexistent_confirmed_at',
                'website_url_nonexistent_confirmed_at',
                'news_page_url_nonexistent_confirmed_at',
                'procurement_page_url_nonexistent_confirmed_at',
            ] as $column) {
                if (Schema::hasColumn('market_organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
