<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('source_maintenance_metric_snapshots')) {
            return;
        }

        Schema::create('source_maintenance_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('country_count')->default(0);
            $table->unsignedInteger('source_slot_count')->default(0);
            $table->unsignedInteger('defined_organization_count')->default(0);
            $table->unsignedInteger('missing_organization_count')->default(0);
            $table->unsignedInteger('url_count')->default(0);
            $table->unsignedInteger('target_url_count')->default(0);
            $table->unsignedInteger('missing_url_count')->default(0);
            $table->unsignedInteger('complete_source_slot_count')->default(0);
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'snapshot_date'], 'source_metric_workspace_date_unique');
            $table->index(['workspace_id', 'snapshot_date'], 'source_metric_workspace_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_maintenance_metric_snapshots');
    }
};
