<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organization_contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organization_contacts', 'notes')) {
                $table->text('notes')->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_organization_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('market_organization_contacts', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
