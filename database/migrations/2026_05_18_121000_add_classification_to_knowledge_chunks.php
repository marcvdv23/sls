<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->string('content_type', 80)->nullable()->after('page_number');
            $table->string('business_area', 120)->nullable()->after('content_type');
            $table->unsignedSmallInteger('answer_priority')->default(50)->after('business_area');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'business_area', 'answer_priority']);
        });
    }
};
