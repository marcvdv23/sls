<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('intelligence_sources', 'procurement_portal_type')) {
                $table->string('procurement_portal_type', 80)->default('not_applicable')->index()->after('source_class');
            }

            if (! Schema::hasColumn('intelligence_sources', 'registration_status')) {
                $table->string('registration_status', 80)->default('none')->index()->after('connector');
            }

            if (! Schema::hasColumn('intelligence_sources', 'registration_notes')) {
                $table->text('registration_notes')->nullable()->after('registration_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_sources', function (Blueprint $table) {
            if (Schema::hasColumn('intelligence_sources', 'registration_notes')) {
                $table->dropColumn('registration_notes');
            }

            if (Schema::hasColumn('intelligence_sources', 'registration_status')) {
                $table->dropColumn('registration_status');
            }

            if (Schema::hasColumn('intelligence_sources', 'procurement_portal_type')) {
                $table->dropColumn('procurement_portal_type');
            }
        });
    }
};
