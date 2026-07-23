<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_image_batches', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('organization_type', 80)->index();
            $table->string('industry', 120)->nullable()->index();
            $table->foreignId('market_crawler_id')->nullable()->constrained('market_crawlers')->nullOnDelete();
            $table->string('status', 80)->default('uploaded')->index();
            $table->unsignedInteger('image_count')->default(0);
            $table->unsignedInteger('entry_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('directory_image_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_image_batch_id')->constrained('directory_image_batches')->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->longText('ocr_text')->nullable();
            $table->string('status', 80)->default('uploaded')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('directory_image_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directory_image_batch_id')->constrained('directory_image_batches')->cascadeOnDelete();
            $table->foreignId('directory_image_page_id')->nullable()->constrained('directory_image_pages')->nullOnDelete();
            $table->foreignId('market_organization_id')->nullable()->constrained('market_organizations')->nullOnDelete();
            $table->string('organization_name')->nullable()->index();
            $table->text('address_text')->nullable();
            $table->string('country_raw')->nullable()->index();
            $table->string('country_normalized')->nullable()->index();
            $table->string('country_iso', 8)->nullable()->index();
            $table->string('website')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->text('executive_text')->nullable();
            $table->longText('raw_text')->nullable();
            $table->unsignedTinyInteger('confidence')->default(50)->index();
            $table->string('review_status', 80)->default('needs_review')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_image_entries');
        Schema::dropIfExists('directory_image_pages');
        Schema::dropIfExists('directory_image_batches');
    }
};
