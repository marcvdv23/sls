<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'award_status')) {
                $table->string('award_status', 40)->nullable()->after('review_status')->index();
            }

            if (! Schema::hasColumn('country_updates', 'award_checked_at')) {
                $table->timestamp('award_checked_at')->nullable()->after('award_status')->index();
            }

            if (! Schema::hasColumn('country_updates', 'award_title')) {
                $table->string('award_title', 500)->nullable()->after('award_checked_at');
            }

            if (! Schema::hasColumn('country_updates', 'award_url')) {
                $table->text('award_url')->nullable()->after('award_title');
            }

            if (! Schema::hasColumn('country_updates', 'award_source_name')) {
                $table->string('award_source_name')->nullable()->after('award_url');
            }

            if (! Schema::hasColumn('country_updates', 'award_date')) {
                $table->date('award_date')->nullable()->after('award_source_name');
            }

            if (! Schema::hasColumn('country_updates', 'award_context')) {
                $table->text('award_context')->nullable()->after('award_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            foreach (['award_context', 'award_date', 'award_source_name', 'award_url', 'award_title', 'award_checked_at', 'award_status'] as $column) {
                if (Schema::hasColumn('country_updates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
