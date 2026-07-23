<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('intelligence_contacts', 'intelligence_document_id')) {
                $table->foreignId('intelligence_document_id')
                    ->nullable()
                    ->after('country_update_id')
                    ->constrained('intelligence_documents')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('intelligence_contacts', 'intelligence_document_id')) {
                $table->dropConstrainedForeignId('intelligence_document_id');
            }
        });
    }
};
