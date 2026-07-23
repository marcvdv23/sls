<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sls_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('task_type', 80)->default('general')->index();
            $table->string('status', 80)->default('open')->index();
            $table->string('priority', 40)->default('normal')->index();
            $table->string('product_focus', 80)->nullable()->index();
            $table->string('country_iso', 8)->nullable()->index();
            $table->unsignedBigInteger('market_organization_id')->nullable()->index();
            $table->unsignedBigInteger('country_update_id')->nullable()->index();
            $table->text('related_url')->nullable();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['task_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sls_tasks');
    }
};
