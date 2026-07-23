<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'student_count')) {
                $table->unsignedInteger('student_count')->nullable()->after('organization_phone')->index();
            }
        });

        if (Schema::hasColumn('market_organizations', 'student_count')) {
            DB::statement("
                UPDATE market_organizations
                SET student_count = CAST(REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(notes, 'Number of students: ', -1), CHAR(10), 1), ',', '') AS UNSIGNED)
                WHERE organization_subcategory = 'school_district'
                  AND student_count IS NULL
                  AND notes LIKE '%Number of students:%'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('market_organizations', 'student_count')) {
                $table->dropColumn('student_count');
            }
        });
    }
};
