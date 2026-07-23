<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_group_id')->nullable()->after('id')->constrained('user_groups')->nullOnDelete();
            $table->string('theme_preference', 32)->default('white')->after('password');
            $table->boolean('is_active')->default(true)->after('theme_preference');
        });

        Schema::create('permission_forms', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('category')->default('General');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('user_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->string('form_key');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_search')->default(false);
            $table->boolean('can_insert')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_print')->default(false);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_import')->default(false);
            $table->boolean('can_run_process')->default(false);
            $table->boolean('can_assign')->default(false);
            $table->boolean('can_configure')->default(false);
            $table->timestamps();

            $table->unique(['user_group_id', 'form_key']);
            $table->foreign('form_key')->references('key')->on('permission_forms')->cascadeOnDelete();
        });

        Schema::create('user_group_country_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('countries')->cascadeOnDelete();
            $table->string('region')->nullable();
            $table->boolean('can_access')->default(true);
            $table->timestamps();

            $table->index(['user_group_id', 'country_id']);
            $table->index(['user_group_id', 'region']);
        });

        Schema::create('user_group_product_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->string('product_key')->nullable();
            $table->boolean('can_access')->default(true);
            $table->timestamps();

            $table->index(['user_group_id', 'product_id']);
            $table->index(['user_group_id', 'product_key']);
        });

        Schema::create('access_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('form_key')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['form_key', 'action']);
            $table->index(['model_type', 'model_id']);
        });

        $now = now();
        $groups = collect([
            ['System Admin', 'Full system access, including security, backups, configuration, and delete rights.', true, true],
            ['Management', 'Broad management access with restricted hard-delete by default.', true, false],
            ['Sales', 'CRM, accounts, contacts, opportunities, reminders, and intelligence review.', true, false],
            ['Research Analyst', 'Tender/news monitoring, sources, contacts, and intelligence qualification.', true, false],
            ['Knowledge Manager', 'Knowledge base, source documents, demo media, OCR, and chat knowledge.', true, false],
            ['Viewer', 'Read-only access to assigned records and dashboards.', true, false],
            ['External / Restricted', 'Restricted access by country, product, and account assignment.', true, false],
        ]);

        $groups->each(function (array $group) use ($now) {
            DB::table('user_groups')->insert([
                'name' => $group[0],
                'slug' => Str::slug($group[0]),
                'description' => $group[1],
                'is_system' => $group[2],
                'is_admin' => $group[3],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        $forms = [
            ['dashboard', 'Dashboard', 'Core'],
            ['crm_search', 'Global CRM Search', 'CRM'],
            ['organizations', 'Organizations / Accounts', 'CRM'],
            ['organization_contacts', 'Organization Contacts', 'CRM'],
            ['organization_tasks', 'Organization Tasks / Reminders', 'CRM'],
            ['intelligence_review', 'Tender & Research Review', 'Intelligence'],
            ['intelligence_sources', 'Intelligence Sources', 'Intelligence'],
            ['intelligence_keywords', 'Intelligence Keywords', 'Intelligence'],
            ['intelligence_contacts', 'Intelligence Contact Directory', 'Intelligence'],
            ['favorites', 'Favorites / Reminders', 'Intelligence'],
            ['knowledge', 'Knowledge Base', 'Knowledge'],
            ['knowledge_approval', 'Knowledge Chunk Approval', 'Knowledge'],
            ['chat', 'Chatbox', 'Knowledge'],
            ['demo_media', 'Demo Media Intelligence', 'Knowledge'],
            ['directory_images', 'Image Directory Import', 'Data Import'],
            ['imports', 'Bulk Imports', 'Data Import'],
            ['backup', 'System Backup', 'Administration'],
            ['security', 'Users, Groups & Permissions', 'Administration'],
            ['crawler_runs', 'Crawler / Monitor Runs', 'Administration'],
        ];

        foreach ($forms as [$key, $label, $category]) {
            DB::table('permission_forms')->insert([
                'key' => $key,
                'label' => $label,
                'category' => $category,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $groupIds = DB::table('user_groups')->pluck('id', 'slug');
        $formKeys = DB::table('permission_forms')->pluck('key');

        foreach ($formKeys as $formKey) {
            DB::table('user_group_permissions')->insert([
                'user_group_id' => $groupIds['system-admin'],
                'form_key' => $formKey,
                'can_view' => true,
                'can_search' => true,
                'can_insert' => true,
                'can_update' => true,
                'can_delete' => true,
                'can_approve' => true,
                'can_print' => true,
                'can_export' => true,
                'can_import' => true,
                'can_run_process' => true,
                'can_assign' => true,
                'can_configure' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $defaultProfiles = [
            'management' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_approve', 'can_print', 'can_export', 'can_import', 'can_run_process', 'can_assign'],
            'sales' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_print', 'can_export', 'can_assign'],
            'research-analyst' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_approve', 'can_print', 'can_export', 'can_import', 'can_run_process'],
            'knowledge-manager' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_approve', 'can_print', 'can_export', 'can_import', 'can_run_process'],
            'viewer' => ['can_view', 'can_search', 'can_print'],
            'external-restricted' => ['can_view', 'can_search', 'can_print'],
        ];

        foreach ($defaultProfiles as $slug => $enabledActions) {
            foreach ($formKeys as $formKey) {
                $row = [
                    'user_group_id' => $groupIds[$slug],
                    'form_key' => $formKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                foreach (['can_view', 'can_search', 'can_insert', 'can_update', 'can_delete', 'can_approve', 'can_print', 'can_export', 'can_import', 'can_run_process', 'can_assign', 'can_configure'] as $action) {
                    $row[$action] = in_array($action, $enabledActions, true);
                }

                if (in_array($formKey, ['backup', 'security'], true) && $slug !== 'management') {
                    foreach (['can_insert', 'can_update', 'can_approve', 'can_export', 'can_import', 'can_run_process', 'can_assign', 'can_configure'] as $action) {
                        $row[$action] = false;
                    }
                }

                DB::table('user_group_permissions')->insert($row);
            }
        }

        $adminGroupId = $groupIds['system-admin'];
        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId) {
            DB::table('users')->where('id', $firstUserId)->update(['user_group_id' => $adminGroupId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('access_audit_logs');
        Schema::dropIfExists('user_group_product_access');
        Schema::dropIfExists('user_group_country_access');
        Schema::dropIfExists('user_group_permissions');
        Schema::dropIfExists('permission_forms');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_group_id');
            $table->dropColumn(['theme_preference', 'is_active']);
        });

        Schema::dropIfExists('user_groups');
    }
};
