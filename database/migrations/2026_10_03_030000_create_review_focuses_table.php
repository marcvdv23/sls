<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_focuses')) {
            return;
        }

        Schema::create('review_focuses', function (Blueprint $table) {
            $table->id();
            $table->string('focus_key', 120)->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('terms')->nullable();
            $table->json('strong_signals')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order'], 'review_focuses_enabled_sort_idx');
            $table->index('is_default', 'review_focuses_default_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_focuses');
    }
};
