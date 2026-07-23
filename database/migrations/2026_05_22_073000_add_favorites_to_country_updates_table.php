<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'is_favorite')) {
                $table->boolean('is_favorite')->default(false)->after('review_status');
            }

            if (! Schema::hasColumn('country_updates', 'favorited_at')) {
                $table->timestamp('favorited_at')->nullable()->after('is_favorite');
            }

            if (! Schema::hasColumn('country_updates', 'favorite_note')) {
                $table->text('favorite_note')->nullable()->after('favorited_at');
            }

            if (! Schema::hasColumn('country_updates', 'reminder_due_at')) {
                $table->dateTime('reminder_due_at')->nullable()->after('favorite_note');
            }

            if (! Schema::hasColumn('country_updates', 'reminder_completed_at')) {
                $table->dateTime('reminder_completed_at')->nullable()->after('reminder_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            foreach (['reminder_completed_at', 'reminder_due_at', 'favorite_note', 'favorited_at', 'is_favorite'] as $column) {
                if (Schema::hasColumn('country_updates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
