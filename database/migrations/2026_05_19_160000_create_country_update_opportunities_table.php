<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_update_opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('issue_area')->index();
            $table->string('opportunity_stage')->default('draft')->index();
            $table->longText('issue_summary');
            $table->longText('product_alignment');
            $table->longText('suggested_email')->nullable();
            $table->json('knowledge_chunk_ids')->nullable();
            $table->json('demo_feature_moment_ids')->nullable();
            $table->json('demo_frame_ids')->nullable();
            $table->timestamps();

            $table->unique(['country_update_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_update_opportunities');
    }
};
