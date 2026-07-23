<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_update_id')->constrained('country_updates')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->text('document_url')->nullable();
            $table->string('document_title', 500)->nullable();
            $table->string('content_type')->nullable();
            $table->string('storage_path', 700)->nullable();
            $table->string('sha256_hash', 64)->index();
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->boolean('is_pdf')->default(false)->index();
            $table->timestamp('fetched_at')->nullable()->index();
            $table->text('extraction_notes')->nullable();
            $table->timestamps();

            $table->unique(['country_update_id', 'sha256_hash'], 'intel_doc_update_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_documents');
    }
};
