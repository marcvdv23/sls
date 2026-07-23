<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_sources', function (Blueprint $table) {
            $table->id();
            $table->string('country_iso', 8)->nullable()->index();
            $table->string('region')->nullable()->index();
            $table->string('name');
            $table->string('domain')->nullable()->index();
            $table->text('url')->nullable();
            $table->string('source_class')->default('local_media')->index();
            $table->string('focus')->default('both')->index();
            $table->string('access_method')->nullable();
            $table->string('connector')->nullable();
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_sources');
    }
};
