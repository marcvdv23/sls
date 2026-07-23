<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('university_survey_targets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country')->nullable()->index();
            $table->string('website_url', 1000);
            $table->string('website_fingerprint', 64)->unique();
            $table->string('domain')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('last_crawled_at')->nullable()->index();
            $table->timestamp('next_crawl_at')->nullable()->index();
            $table->unsignedInteger('pages_checked')->default(0);
            $table->unsignedInteger('contacts_found')->default(0);
            $table->json('published_email_patterns')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('university_survey_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_survey_target_id')->constrained('university_survey_targets')->cascadeOnDelete();
            $table->string('role_category')->default('general')->index();
            $table->string('person_name')->nullable()->index();
            $table->string('job_title')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('email_status')->default('published')->index();
            $table->string('organization')->nullable();
            $table->string('source_url', 1000);
            $table->text('context_excerpt')->nullable();
            $table->decimal('confidence_score', 4, 2)->default(0);
            $table->string('source_fingerprint', 64)->unique();
            $table->timestamp('found_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('university_survey_contacts');
        Schema::dropIfExists('university_survey_targets');
    }
};
