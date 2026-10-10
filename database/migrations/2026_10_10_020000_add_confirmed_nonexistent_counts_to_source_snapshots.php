<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('source_maintenance_metric_snapshots')) {
            return;
        }

        Schema::table('source_maintenance_metric_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('source_maintenance_metric_snapshots', 'confirmed_nonexistent_organization_count')) {
                $table->unsignedInteger('confirmed_nonexistent_organization_count')->default(0)->after('defined_organization_count');
            }

            if (! Schema::hasColumn('source_maintenance_metric_snapshots', 'confirmed_nonexistent_url_count')) {
                $table->unsignedInteger('confirmed_nonexistent_url_count')->default(0)->after('url_count');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('source_maintenance_metric_snapshots')) {
            return;
        }

        Schema::table('source_maintenance_metric_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('source_maintenance_metric_snapshots', 'confirmed_nonexistent_organization_count')) {
                $table->dropColumn('confirmed_nonexistent_organization_count');
            }

            if (Schema::hasColumn('source_maintenance_metric_snapshots', 'confirmed_nonexistent_url_count')) {
                $table->dropColumn('confirmed_nonexistent_url_count');
            }
        });
    }
};
