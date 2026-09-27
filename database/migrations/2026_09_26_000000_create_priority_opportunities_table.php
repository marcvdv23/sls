<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('priority_opportunities')) {
            Schema::create('priority_opportunities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('primary_organization_id')->nullable()->constrained('market_organizations')->nullOnDelete();
                $table->foreignId('primary_task_id')->nullable()->constrained('sls_tasks')->nullOnDelete();
                $table->foreignId('country_update_id')->nullable()->constrained('country_updates')->nullOnDelete();
                $table->string('country_market')->index();
                $table->string('country_iso', 8)->nullable()->index();
                $table->string('region')->nullable()->index();
                $table->string('focus_tier', 120)->nullable()->index();
                $table->string('institution')->index();
                $table->text('reform_development')->nullable();
                $table->string('stage_2026')->nullable()->index();
                $table->text('why_relevant')->nullable();
                $table->text('evidence_scale')->nullable();
                $table->text('donor_support')->nullable();
                $table->string('evidence_confidence', 80)->nullable()->index();
                $table->text('recommended_next_action')->nullable();
                $table->text('source_1')->nullable();
                $table->text('source_2')->nullable();
                $table->string('origin')->nullable()->index();
                $table->text('review_notes')->nullable();
                $table->string('status', 80)->default('new')->index();
                $table->string('priority', 40)->default('high')->index();
                $table->string('product_focus', 80)->default('SSAS')->index();
                $table->timestamp('next_follow_up_at')->nullable()->index();
                $table->timestamp('last_activity_at')->nullable()->index();
                $table->string('source_fingerprint', 64)->unique();
                $table->timestamps();
            });
        }

        Schema::dropIfExists('market_organization_priority_opportunity');
        Schema::create('market_organization_priority_opportunity', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('priority_opportunity_id');
            $table->unsignedBigInteger('market_organization_id');
            $table->string('relationship_type', 80)->default('target_account')->index();
            $table->timestamps();

            $table->unique(['priority_opportunity_id', 'market_organization_id'], 'priority_org_unique');
            $table->foreign('priority_opportunity_id', 'priority_org_priority_fk')->references('id')->on('priority_opportunities')->cascadeOnDelete();
            $table->foreign('market_organization_id', 'priority_org_market_org_fk')->references('id')->on('market_organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_organization_priority_opportunity');
        Schema::dropIfExists('priority_opportunities');
    }
};
