<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            $table->text('summary_english')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('country_updates', function (Blueprint $table) {
            $table->dropColumn('summary_english');
        });
    }
};
