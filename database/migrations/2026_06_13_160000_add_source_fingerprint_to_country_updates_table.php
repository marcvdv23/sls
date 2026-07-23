<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            if (! Schema::hasColumn('country_updates', 'source_fingerprint')) {
                $table->string('source_fingerprint', 64)->nullable()->after('source_url');
            }
        });

        Schema::table('country_updates', function (Blueprint $table) {
            $table->unique(['country_id', 'source_fingerprint'], 'country_updates_country_source_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            $table->dropUnique('country_updates_country_source_fingerprint_unique');
        });

        Schema::table('country_updates', function (Blueprint $table) {
            if (Schema::hasColumn('country_updates', 'source_fingerprint')) {
                $table->dropColumn('source_fingerprint');
            }
        });
    }
};