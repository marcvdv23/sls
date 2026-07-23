<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'map_opened_at')) {
                $table->timestamp('map_opened_at')->nullable()->after('reminder_completed_at')->index();
            }

            if (! Schema::hasColumn('country_updates', 'map_opened_by_user_id')) {
                $table->foreignId('map_opened_by_user_id')->nullable()->after('map_opened_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('country_updates', 'map_action_status')) {
                $table->string('map_action_status')->nullable()->after('map_opened_by_user_id')->index();
            }

            if (! Schema::hasColumn('country_updates', 'map_action_note')) {
                $table->text('map_action_note')->nullable()->after('map_action_status');
            }

            if (! Schema::hasColumn('country_updates', 'map_processed_at')) {
                $table->timestamp('map_processed_at')->nullable()->after('map_action_note')->index();
            }

            if (! Schema::hasColumn('country_updates', 'map_processed_by_user_id')) {
                $table->foreignId('map_processed_by_user_id')->nullable()->after('map_processed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (Schema::hasColumn('country_updates', 'map_processed_by_user_id')) {
                $table->dropConstrainedForeignId('map_processed_by_user_id');
            }

            if (Schema::hasColumn('country_updates', 'map_processed_at')) {
                $table->dropColumn('map_processed_at');
            }

            if (Schema::hasColumn('country_updates', 'map_action_note')) {
                $table->dropColumn('map_action_note');
            }

            if (Schema::hasColumn('country_updates', 'map_action_status')) {
                $table->dropColumn('map_action_status');
            }

            if (Schema::hasColumn('country_updates', 'map_opened_by_user_id')) {
                $table->dropConstrainedForeignId('map_opened_by_user_id');
            }

            if (Schema::hasColumn('country_updates', 'map_opened_at')) {
                $table->dropColumn('map_opened_at');
            }
        });
    }
};
