<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_answer_logs', function (Blueprint $table) {
            $table->json('visual_matches')->nullable()->after('source_matches');
        });
    }

    public function down(): void
    {
        Schema::table('chat_answer_logs', function (Blueprint $table) {
            $table->dropColumn('visual_matches');
        });
    }
};
