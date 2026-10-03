<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('priority_opportunity_options')) {
            return;
        }

        Schema::create('priority_opportunity_options', function (Blueprint $table) {
            $table->id();
            $table->string('option_group', 80)->index();
            $table->string('option_key', 120);
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['option_group', 'option_key'], 'priority_option_group_key_unique');
            $table->index(['option_group', 'is_enabled', 'sort_order'], 'priority_option_group_enabled_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('priority_opportunity_options');
    }
};
