<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_security_admin_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_document_id');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('country_iso', 8)->nullable()->index();
            $table->string('country_name')->nullable()->index();
            $table->string('organization_name')->index();
            $table->text('role_in_programme')->nullable();
            $table->text('related_programmes')->nullable();
            $table->text('evidence_excerpt')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(70);
            $table->string('status', 80)->default('staged')->index();
            $table->unsignedBigInteger('market_organization_id')->nullable();
            $table->timestamps();

            $table->foreign('source_document_id', 'ss_admin_candidates_doc_fk')->references('id')->on('source_documents')->cascadeOnDelete();
            $table->foreign('country_id', 'ss_admin_candidates_country_fk')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('market_organization_id', 'ss_admin_candidates_org_fk')->references('id')->on('market_organizations')->nullOnDelete();
            $table->unique(['source_document_id', 'country_iso', 'organization_name'], 'ss_admin_candidates_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_security_admin_candidates');
    }
};

