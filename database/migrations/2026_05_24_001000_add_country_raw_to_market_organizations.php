<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'country_raw')) {
                $table->string('country_raw')->nullable()->index()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('market_organizations', 'country_raw')) {
                $table->dropColumn('country_raw');
            }
        });
    }
};
