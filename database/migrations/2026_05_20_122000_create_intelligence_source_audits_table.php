<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_source_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intelligence_source_id')->nullable()->constrained('intelligence_sources')->nullOnDelete();
            $table->string('source_name')->nullable();
            $table->string('domain')->nullable()->index();
            $table->string('focus')->nullable()->index();
            $table->string('method')->default('GET');
            $table->text('request_url')->nullable();
            $table->text('request_payload')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->boolean('ok')->default(false)->index();
            $table->unsignedInteger('items_found')->default(0);
            $table->mediumText('response_excerpt')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_source_audits');
    }
};
