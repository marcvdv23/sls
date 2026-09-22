<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serpapi_search_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('focus', 80)->default('social_security');
            $table->text('query_template');
            $table->json('keywords')->nullable();
            $table->unsignedInteger('results_per_country')->default(10);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serpapi_search_templates');
    }
};
