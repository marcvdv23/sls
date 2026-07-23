<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_security_admin_candidate_contexts', function (Blueprint $table) {
            $table->json('normalized_roles')->nullable()->after('related_programmes');
            $table->json('programme_l1')->nullable()->after('normalized_roles');
            $table->json('programme_l2')->nullable()->after('programme_l1');
            $table->json('employer_types')->nullable()->after('programme_l2');
            $table->boolean('special_system_mentioned')->default(false)->after('employer_types');
            $table->json('special_system_employer_types')->nullable()->after('special_system_mentioned');
            $table->boolean('needs_enrichment')->default(false)->after('special_system_employer_types');
            $table->text('classification_notes')->nullable()->after('needs_enrichment');
        });
    }

    public function down(): void
    {
        Schema::table('social_security_admin_candidate_contexts', function (Blueprint $table) {
            $table->dropColumn([
                'normalized_roles',
                'programme_l1',
                'programme_l2',
                'employer_types',
                'special_system_mentioned',
                'special_system_employer_types',
                'needs_enrichment',
                'classification_notes',
            ]);
        });
    }
};
