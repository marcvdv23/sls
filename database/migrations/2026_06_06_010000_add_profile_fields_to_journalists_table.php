<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journalists', function (Blueprint $table) {
            if (! Schema::hasColumn('journalists', 'profile_summary')) {
                $table->text('profile_summary')->nullable()->after('discovery_status');
            }

            if (! Schema::hasColumn('journalists', 'background')) {
                $table->text('background')->nullable()->after('profile_summary');
            }

            if (! Schema::hasColumn('journalists', 'education')) {
                $table->text('education')->nullable()->after('background');
            }

            if (! Schema::hasColumn('journalists', 'profile_sources')) {
                $table->json('profile_sources')->nullable()->after('education');
            }
        });
    }

    public function down(): void
    {
        Schema::table('journalists', function (Blueprint $table) {
            foreach (['profile_sources', 'education', 'background', 'profile_summary'] as $column) {
                if (Schema::hasColumn('journalists', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
