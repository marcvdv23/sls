<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_security_admin_name_suppressions')) {
            Schema::create('social_security_admin_name_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('country_iso', 8);
                $table->string('name');
                $table->string('name_normalized');
                $table->string('reason', 80)->default('duplicate');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['country_iso', 'name_normalized'], 'ss_admin_suppression_country_name_unique');
                $table->index(['country_iso', 'reason'], 'ss_admin_suppression_country_reason_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_security_admin_name_suppressions');
    }
};
