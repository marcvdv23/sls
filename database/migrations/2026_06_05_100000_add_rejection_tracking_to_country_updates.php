<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'rejection_reason_code')) {
                $table->string('rejection_reason_code')->nullable()->after('review_status')->index();
            }

            if (! Schema::hasColumn('country_updates', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejection_reason_code');
            }

            if (! Schema::hasColumn('country_updates', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejection_reason')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (Schema::hasColumn('country_updates', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }

            if (Schema::hasColumn('country_updates', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }

            if (Schema::hasColumn('country_updates', 'rejection_reason_code')) {
                $table->dropColumn('rejection_reason_code');
            }
        });
    }
};
