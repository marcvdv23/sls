<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('journalists')) {
            Schema::create('journalists', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('name_normalized')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->string('publication_name')->nullable()->index();
                $table->foreignId('publication_country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->string('publication_country_name')->nullable();
                $table->json('topics')->nullable();
                $table->string('source_fingerprint')->nullable()->unique();
                $table->string('discovery_status')->default('needs_review')->index();
                $table->text('notes')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('journalist_articles')) {
            Schema::create('journalist_articles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journalist_id')->constrained('journalists')->cascadeOnDelete();
                $table->foreignId('country_update_id')->constrained('country_updates')->cascadeOnDelete();
                $table->string('article_title', 500)->nullable();
                $table->string('article_url', 1000)->nullable();
                $table->string('publication_name')->nullable();
                $table->foreignId('publication_country_id')->nullable()->constrained('countries')->nullOnDelete();
                $table->json('topics')->nullable();
                $table->string('author_name_raw')->nullable();
                $table->string('author_email_raw')->nullable();
                $table->text('praise_note')->nullable();
                $table->string('outreach_status')->default('draft')->index();
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();

                $table->unique(['journalist_id', 'country_update_id'], 'journalist_articles_unique_story');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journalist_articles');
        Schema::dropIfExists('journalists');
    }
};
