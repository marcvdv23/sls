<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('directory_image_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('directory_image_pages', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('storage_path')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('directory_image_pages', function (Blueprint $table) {
            if (Schema::hasColumn('directory_image_pages', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });
    }
};
