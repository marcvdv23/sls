<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'country_resolution_status')) {
                $table->string('country_resolution_status', 80)->default('resolved')->index()->after('country_iso');
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('market_organizations', 'country_resolution_status')) {
                $table->dropColumn('country_resolution_status');
            }
        });
    }
};
