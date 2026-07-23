<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_answer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ai_provider', 50)->nullable();
            $table->string('answer_language', 50)->nullable();
            $table->text('question');
            $table->longText('answer');
            $table->json('retrieval_terms')->nullable();
            $table->json('source_matches')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_answer_logs');
    }
};
