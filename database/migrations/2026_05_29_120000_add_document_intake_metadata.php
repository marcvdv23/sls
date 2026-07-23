<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('source_documents', 'intake_category')) {
                $table->string('intake_category', 80)->nullable()->after('source_type')->index();
            }

            if (! Schema::hasColumn('source_documents', 'intake_action')) {
                $table->string('intake_action', 80)->nullable()->after('intake_category')->index();
            }

            if (! Schema::hasColumn('source_documents', 'contact_relationship_type')) {
                $table->string('contact_relationship_type', 80)->nullable()->after('intake_action')->index();
            }

            if (! Schema::hasColumn('source_documents', 'related_country_id')) {
                $table->unsignedBigInteger('related_country_id')->nullable()->after('contact_relationship_type')->index();
            }

            if (! Schema::hasColumn('source_documents', 'related_organization_name')) {
                $table->string('related_organization_name')->nullable()->after('related_country_id')->index();
            }

            if (! Schema::hasColumn('source_documents', 'intake_notes')) {
                $table->text('intake_notes')->nullable()->after('related_organization_name');
            }
        });

        Schema::table('intelligence_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('intelligence_contacts', 'source_document_id')) {
                $table->unsignedBigInteger('source_document_id')->nullable()->after('intelligence_document_id')->index();
            }

            if (! Schema::hasColumn('intelligence_contacts', 'relationship_type')) {
                $table->string('relationship_type', 80)->nullable()->after('job_title')->index();
            }

            if (! Schema::hasColumn('intelligence_contacts', 'contact_status')) {
                $table->string('contact_status', 80)->nullable()->after('relationship_type')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_contacts', function (Blueprint $table) {
            foreach (['contact_status', 'relationship_type', 'source_document_id'] as $column) {
                if (Schema::hasColumn('intelligence_contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('source_documents', function (Blueprint $table) {
            foreach (['intake_notes', 'related_organization_name', 'related_country_id', 'contact_relationship_type', 'intake_action', 'intake_category'] as $column) {
                if (Schema::hasColumn('source_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
