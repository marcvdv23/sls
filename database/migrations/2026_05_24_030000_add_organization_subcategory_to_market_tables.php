<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'organization_subcategory')) {
                $table->string('organization_subcategory', 120)->nullable()->after('industry')->index();
            }
        });

        Schema::table('directory_image_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('directory_image_batches', 'organization_subcategory')) {
                $table->string('organization_subcategory', 120)->nullable()->after('industry')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('directory_image_batches', function (Blueprint $table) {
            if (Schema::hasColumn('directory_image_batches', 'organization_subcategory')) {
                $table->dropColumn('organization_subcategory');
            }
        });

        Schema::table('market_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('market_organizations', 'organization_subcategory')) {
                $table->dropColumn('organization_subcategory');
            }
        });
    }
};
