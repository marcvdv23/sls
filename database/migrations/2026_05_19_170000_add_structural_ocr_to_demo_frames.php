<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_frames', function (Blueprint $table) {
            $table->longText('cleaned_ui_text')->nullable()->after('ocr_text');
            $table->json('ui_structure')->nullable()->after('detected_ui_terms');
            $table->text('screen_summary')->nullable()->after('ui_structure');
        });
    }

    public function down(): void
    {
        Schema::table('demo_frames', function (Blueprint $table) {
            $table->dropColumn(['cleaned_ui_text', 'ui_structure', 'screen_summary']);
        });
    }
};
