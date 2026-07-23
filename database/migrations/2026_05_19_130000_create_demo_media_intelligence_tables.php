<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('source_type')->default('product_demo_video');
            $table->string('language_code', 10)->default('en');
            $table->string('video_storage_path')->nullable();
            $table->string('transcript_storage_path')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('processing_status')->default('draft')->index();
            $table->text('processing_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_session_id')->constrained()->cascadeOnDelete();
            $table->integer('start_ms')->index();
            $table->integer('end_ms')->nullable()->index();
            $table->string('speaker_label')->nullable();
            $table->longText('transcript_text');
            $table->timestamps();
        });

        Schema::create('demo_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_session_id')->constrained()->cascadeOnDelete();
            $table->integer('timestamp_ms')->index();
            $table->string('image_storage_path');
            $table->longText('ocr_text')->nullable();
            $table->json('detected_ui_terms')->nullable();
            $table->string('review_status')->default('unreviewed')->index();
            $table->timestamps();
        });

        Schema::create('demo_feature_moments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature_name')->index();
            $table->string('business_problem')->nullable()->index();
            $table->string('module_name')->nullable()->index();
            $table->integer('start_ms')->nullable()->index();
            $table->integer('end_ms')->nullable()->index();
            $table->longText('summary')->nullable();
            $table->json('benefits')->nullable();
            $table->json('frame_ids')->nullable();
            $table->string('approval_status')->default('unreviewed')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_feature_moments');
        Schema::dropIfExists('demo_frames');
        Schema::dropIfExists('demo_transcript_segments');
        Schema::dropIfExists('demo_sessions');
    }
};
