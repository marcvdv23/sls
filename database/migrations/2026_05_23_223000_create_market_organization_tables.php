<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_crawlers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('crawler_key', 120)->unique();
            $table->string('crawler_type', 80)->index();
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_run_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('market_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_crawler_id')->nullable()->constrained('market_crawlers')->nullOnDelete();
            $table->string('name')->index();
            $table->string('name_normalized', 500)->index();
            $table->string('organization_type', 80)->index();
            $table->string('industry', 120)->nullable()->index();
            $table->string('country')->nullable()->index();
            $table->string('country_iso', 8)->nullable()->index();
            $table->string('region')->nullable()->index();
            $table->text('website_url')->nullable();
            $table->string('website_domain')->nullable()->index();
            $table->string('organization_phone')->nullable();
            $table->text('procurement_page_url')->nullable();
            $table->text('leadership_page_url')->nullable();
            $table->text('hr_page_url')->nullable();
            $table->text('it_page_url')->nullable();
            $table->string('status', 80)->default('active')->index();
            $table->timestamp('last_crawled_at')->nullable()->index();
            $table->string('last_crawler_name')->nullable();
            $table->timestamp('next_crawl_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_fingerprint', 64)->unique();
            $table->timestamps();
        });

        Schema::create('market_organization_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_organization_id')->constrained('market_organizations')->cascadeOnDelete();
            $table->foreignId('market_crawler_id')->nullable()->constrained('market_crawlers')->nullOnDelete();
            $table->string('contact_type', 80)->default('general')->index();
            $table->string('person_name')->nullable()->index();
            $table->string('job_title')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable();
            $table->text('source_url')->nullable();
            $table->text('context_excerpt')->nullable();
            $table->string('verification_status', 80)->default('published')->index();
            $table->timestamp('extracted_at')->nullable()->index();
            $table->string('source_fingerprint', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_organization_contacts');
        Schema::dropIfExists('market_organizations');
        Schema::dropIfExists('market_crawlers');
    }
};
