<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tender_awarded_companies')) {
            return;
        }

        Schema::create('tender_awarded_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_update_id')->nullable()->constrained('country_updates')->nullOnDelete();
            $table->foreignId('market_organization_id')->nullable()->constrained('market_organizations')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('company_name');
            $table->string('country_name')->nullable();
            $table->string('country_iso', 8)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 80)->nullable();
            $table->string('website_url', 1000)->nullable();
            $table->string('contract_title', 500)->nullable();
            $table->string('contract_reference')->nullable();
            $table->string('contract_value')->nullable();
            $table->string('currency', 16)->nullable();
            $table->date('award_date')->nullable();
            $table->string('award_url', 1500)->nullable();
            $table->string('source_name')->nullable();
            $table->text('contract_info')->nullable();
            $table->string('relationship_status', 40)->default('awarded_contractor');
            $table->text('notes')->nullable();
            $table->string('source_fingerprint', 64)->unique();
            $table->timestamps();

            $table->index(['country_iso', 'relationship_status']);
            $table->index(['product_id', 'relationship_status']);
            $table->index('award_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_awarded_companies');
    }
};