<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_monitor_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('focus')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->json('sources_checked')->nullable();
            $table->unsignedInteger('items_found')->default(0);
            $table->string('status')->default('completed')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['country_id', 'focus', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_monitor_runs');
    }
};
