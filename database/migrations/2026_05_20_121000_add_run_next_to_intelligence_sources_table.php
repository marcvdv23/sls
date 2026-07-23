<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('intelligence_sources', 'force_next_at')) {
                $table->timestamp('force_next_at')->nullable()->index()->after('last_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_sources', function (Blueprint $table) {
            if (Schema::hasColumn('intelligence_sources', 'force_next_at')) {
                $table->dropColumn('force_next_at');
            }
        });
    }
};
