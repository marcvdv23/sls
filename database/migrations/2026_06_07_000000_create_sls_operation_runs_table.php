<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sls_operation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('operation_key', 120)->index();
            $table->string('operation_name', 180);
            $table->string('status', 40)->default('queued')->index();
            $table->json('parameters')->nullable();
            $table->json('summary')->nullable();
            $table->json('items')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->boolean('dry_run')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sls_operation_runs');
    }
};
