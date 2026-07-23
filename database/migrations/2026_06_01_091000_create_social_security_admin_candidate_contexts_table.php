<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_security_admin_candidate_contexts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('social_security_admin_candidate_id');
            $table->string('context_key', 64);
            $table->text('role_in_programme')->nullable();
            $table->text('related_programmes')->nullable();
            $table->text('evidence_excerpt')->nullable();
            $table->unsignedTinyInteger('confidence_score')->default(70);
            $table->timestamps();

            $table->foreign('social_security_admin_candidate_id', 'ss_admin_contexts_candidate_fk')
                ->references('id')
                ->on('social_security_admin_candidates')
                ->cascadeOnDelete();
            $table->unique(['social_security_admin_candidate_id', 'context_key'], 'ss_admin_contexts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_security_admin_candidate_contexts');
    }
};

