<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('focus')->index();
            $table->string('term');
            $table->string('language_code', 12)->default('en')->index();
            $table->string('category')->nullable()->index();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();

            $table->unique(['focus', 'term', 'language_code'], 'intelligence_keywords_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_keywords');
    }
};
