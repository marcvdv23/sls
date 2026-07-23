<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intelligence_monitor_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('country_iso', 8)->index();
            $table->string('country_name')->nullable();
            $table->string('focus')->default('social_security')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('requested_at')->nullable()->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['country_iso', 'focus', 'status'], 'monitor_priority_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_monitor_priorities');
    }
};
