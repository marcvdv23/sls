<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_update_id')->nullable()->constrained('country_updates')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->text('document_url')->nullable();
            $table->string('document_title', 500)->nullable();
            $table->string('organization')->nullable();
            $table->string('person_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email')->index();
            $table->text('context_excerpt')->nullable();
            $table->string('source_fingerprint', 64)->unique();
            $table->timestamp('extracted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_contacts');
    }
};
