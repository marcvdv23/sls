<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'title_english')) {
                $table->string('title_english', 500)->nullable()->after('title');
            }

            if (! Schema::hasColumn('country_updates', 'title_original')) {
                $table->string('title_original', 500)->nullable()->after('title_english');
            }
        });

        DB::table('country_updates')
            ->whereNull('title_english')
            ->update(['title_english' => DB::raw('title')]);

        DB::table('country_updates')
            ->whereNull('title_original')
            ->update(['title_original' => DB::raw('title')]);
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (Schema::hasColumn('country_updates', 'title_original')) {
                $table->dropColumn('title_original');
            }

            if (Schema::hasColumn('country_updates', 'title_english')) {
                $table->dropColumn('title_english');
            }
        });
    }
};
