<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('market_organizations', 'lead_status')) {
                $table->string('lead_status', 80)->default('unqualified')->index()->after('status');
            }

            if (! Schema::hasColumn('market_organizations', 'lead_source')) {
                $table->string('lead_source')->nullable()->index()->after('lead_status');
            }

            if (! Schema::hasColumn('market_organizations', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->index()->after('next_crawl_at');
            }
        });

        Schema::create('market_organization_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_organization_id');
            $table->string('activity_type', 80)->default('note')->index();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('activity_at')->nullable()->index();
            $table->string('logged_by')->nullable();
            $table->timestamps();

            $table->foreign('market_organization_id', 'mo_activities_org_fk')->references('id')->on('market_organizations')->cascadeOnDelete();
        });

        Schema::create('market_organization_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_organization_id');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('task_type', 80)->default('follow_up')->index();
            $table->string('status', 80)->default('open')->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('market_organization_id', 'mo_tasks_org_fk')->references('id')->on('market_organizations')->cascadeOnDelete();
        });

        Schema::create('market_organization_communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_organization_id');
            $table->unsignedBigInteger('market_organization_contact_id')->nullable();
            $table->string('channel', 80)->default('email')->index();
            $table->string('direction', 80)->default('outbound')->index();
            $table->string('subject')->nullable();
            $table->text('body_excerpt')->nullable();
            $table->string('from_address')->nullable()->index();
            $table->string('to_address')->nullable()->index();
            $table->timestamp('sent_or_received_at')->nullable()->index();
            $table->text('source_reference')->nullable();
            $table->timestamps();

            $table->foreign('market_organization_id', 'mo_comms_org_fk')->references('id')->on('market_organizations')->cascadeOnDelete();
            $table->foreign('market_organization_contact_id', 'mo_comms_contact_fk')->references('id')->on('market_organization_contacts')->nullOnDelete();
        });

        Schema::create('market_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_name');
            $table->string('email_address')->index();
            $table->string('provider', 80)->nullable()->index();
            $table->string('sync_status', 80)->default('not_configured')->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_email_accounts');
        Schema::dropIfExists('market_organization_communications');
        Schema::dropIfExists('market_organization_tasks');
        Schema::dropIfExists('market_organization_activities');

        Schema::table('market_organizations', function (Blueprint $table) {
            if (Schema::hasColumn('market_organizations', 'last_contacted_at')) {
                $table->dropColumn('last_contacted_at');
            }

            if (Schema::hasColumn('market_organizations', 'lead_source')) {
                $table->dropColumn('lead_source');
            }

            if (Schema::hasColumn('market_organizations', 'lead_status')) {
                $table->dropColumn('lead_status');
            }
        });
    }
};
