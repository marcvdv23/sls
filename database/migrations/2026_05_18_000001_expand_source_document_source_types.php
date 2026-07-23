<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE source_documents MODIFY source_type ENUM('manual','email','email_attachment','rfp','brochure','implementation_note','security_document','country_source','news','law','policy','webinar_transcript','product_demo_transcript','country_specific_information','organization_specific_information') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE source_documents MODIFY source_type ENUM('manual','email','email_attachment','rfp','brochure','implementation_note','security_document','country_source','news','law','policy') NOT NULL");
    }
};
