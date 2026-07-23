<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'archive_read_at')) {
                $table->timestamp('archive_read_at')->nullable()->after('map_processed_by_user_id')->index();
            }

            if (! Schema::hasColumn('country_updates', 'archive_read_by_user_id')) {
                $table->foreignId('archive_read_by_user_id')->nullable()->after('archive_read_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('country_updates', 'archive_note')) {
                $table->text('archive_note')->nullable()->after('archive_read_by_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (Schema::hasColumn('country_updates', 'archive_note')) {
                $table->dropColumn('archive_note');
            }

            if (Schema::hasColumn('country_updates', 'archive_read_by_user_id')) {
                $table->dropConstrainedForeignId('archive_read_by_user_id');
            }

            if (Schema::hasColumn('country_updates', 'archive_read_at')) {
                $table->dropColumn('archive_read_at');
            }
        });
    }
};
