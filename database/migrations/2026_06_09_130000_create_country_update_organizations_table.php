<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_update_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_update_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_organization_id')->constrained()->cascadeOnDelete();
            $table->string('context', 80)->default('mentioned');
            $table->timestamps();

            $table->unique(['country_update_id', 'market_organization_id'], 'country_update_org_unique');
            $table->index(['market_organization_id', 'context'], 'country_update_org_context_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_update_organizations');
    }
};
