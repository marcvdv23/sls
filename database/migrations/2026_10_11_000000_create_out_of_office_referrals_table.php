<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('out_of_office_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('mailbox')->index();
            $table->string('message_uid', 80)->nullable()->index();
            $table->string('message_id')->nullable()->index();
            $table->timestamp('message_date')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable()->index();
            $table->string('referred_name')->nullable()->index();
            $table->string('referred_email')->nullable()->index();
            $table->string('referred_job_title')->nullable();
            $table->string('referred_organization')->nullable()->index();
            $table->text('context_excerpt')->nullable();
            $table->text('raw_excerpt')->nullable();
            $table->string('status', 80)->default('needs_review')->index();
            $table->string('source_fingerprint')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_of_office_referrals');
    }
};
