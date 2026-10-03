<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serpapi_search_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('serpapi_search_templates', 'required_terms')) {
                $table->json('required_terms')->nullable()->after('keywords');
            }

            if (! Schema::hasColumn('serpapi_search_templates', 'blocked_domains')) {
                $table->json('blocked_domains')->nullable()->after('required_terms');
            }

            if (! Schema::hasColumn('serpapi_search_templates', 'blocked_path_terms')) {
                $table->json('blocked_path_terms')->nullable()->after('blocked_domains');
            }

            if (! Schema::hasColumn('serpapi_search_templates', 'vendor_terms')) {
                $table->json('vendor_terms')->nullable()->after('blocked_path_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('serpapi_search_templates', function (Blueprint $table) {
            foreach (['vendor_terms', 'blocked_path_terms', 'blocked_domains', 'required_terms'] as $column) {
                if (Schema::hasColumn('serpapi_search_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
