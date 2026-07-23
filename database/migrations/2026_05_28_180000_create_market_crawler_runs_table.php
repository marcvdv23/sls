<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'news_page_url')) {
                $table->text('news_page_url')->nullable()->after('it_page_url');
            }
        });

        Schema::create('market_crawler_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_crawler_id')->constrained('market_crawlers')->cascadeOnDelete();
            $table->foreignId('market_organization_id')->nullable()->constrained('market_organizations')->nullOnDelete();
            $table->string('run_type', 80)->index();
            $table->string('status', 80)->default('started')->index();
            $table->text('query_text')->nullable();
            $table->text('request_url')->nullable();
            $table->text('url_checked')->nullable();
            $table->integer('http_status')->nullable();
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('urls_updated')->default(0);
            $table->unsignedInteger('contacts_found')->default(0);
            $table->json('result_payload')->nullable();
            $table->text('response_excerpt')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_crawler_runs');

        Schema::table('market_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('market_organizations', 'news_page_url')) {
                $table->dropColumn('news_page_url');
            }
        });
    }
};
