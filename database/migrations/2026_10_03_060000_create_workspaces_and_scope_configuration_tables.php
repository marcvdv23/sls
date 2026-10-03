<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            Schema::create('workspaces', function (Blueprint $table) {
                $table->id();
                $table->string('workspace_key', 120)->unique();
                $table->string('entity_key', 120)->default('2interact')->index();
                $table->string('entity_name')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('domain_label')->nullable();
                $table->string('status', 40)->default('active')->index();
                $table->boolean('is_default')->default(false)->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        $workspaceId = $this->defaultWorkspaceId();

        foreach ($this->scopedTables() as $table => $afterColumn) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'workspace_id')) {
                Schema::table($table, function (Blueprint $schema) use ($afterColumn) {
                    $schema->foreignId('workspace_id')
                        ->nullable()
                        ->after($afterColumn)
                        ->constrained('workspaces')
                        ->nullOnDelete();
                });
            }

            DB::table($table)->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);
        }

        $this->replaceUniqueIndexes();
    }

    public function down(): void
    {
        $this->restoreUniqueIndexes();

        foreach (array_reverse(array_keys($this->scopedTables())) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $schema) {
                $schema->dropConstrainedForeignId('workspace_id');
            });
        }

        Schema::dropIfExists('workspaces');
    }

    private function defaultWorkspaceId(): int
    {
        $key = config('sls.workspace.key', 'social_security');

        $existingId = DB::table('workspaces')->where('workspace_key', $key)->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        return (int) DB::table('workspaces')->insertGetId([
            'workspace_key' => $key,
            'entity_key' => config('sls.entity.key', '2interact'),
            'entity_name' => config('sls.entity.display_name', config('sls.entity.name', '2interact')),
            'name' => config('sls.workspace.name', 'Social Security Sales'),
            'description' => config('sls.workspace.description', 'Social security, pensions, public sector HR/payroll, and related sales intelligence.'),
            'domain_label' => config('sls.workspace.domain_label', 'Social Security and Public Sector Software'),
            'status' => 'active',
            'is_default' => true,
            'metadata' => json_encode(['source' => 'migration_seed']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function scopedTables(): array
    {
        return [
            'products' => 'id',
            'review_focuses' => 'id',
            'priority_opportunity_options' => 'id',
            'serpapi_search_templates' => 'id',
            'crawler_settings' => 'id',
            'sls_settings' => 'id',
        ];
    }

    private function replaceUniqueIndexes(): void
    {
        if (Schema::hasTable('review_focuses')) {
            $this->dropIndexIfExists('review_focuses', 'review_focuses_focus_key_unique');
            Schema::table('review_focuses', function (Blueprint $table) {
                $table->unique(['workspace_id', 'focus_key'], 'review_focuses_workspace_key_unique');
            });
        }

        if (Schema::hasTable('priority_opportunity_options')) {
            $this->dropIndexIfExists('priority_opportunity_options', 'priority_option_group_key_unique');
            Schema::table('priority_opportunity_options', function (Blueprint $table) {
                $table->unique(['workspace_id', 'option_group', 'option_key'], 'priority_option_workspace_group_key_unique');
            });
        }

        if (Schema::hasTable('crawler_settings')) {
            $this->dropIndexIfExists('crawler_settings', 'crawler_settings_setting_key_unique');
            Schema::table('crawler_settings', function (Blueprint $table) {
                $table->unique(['workspace_id', 'setting_key'], 'crawler_settings_workspace_key_unique');
            });
        }

        if (Schema::hasTable('sls_settings')) {
            $this->dropIndexIfExists('sls_settings', 'sls_settings_setting_key_unique');
            Schema::table('sls_settings', function (Blueprint $table) {
                $table->unique(['workspace_id', 'setting_key'], 'sls_settings_workspace_key_unique');
            });
        }
    }

    private function restoreUniqueIndexes(): void
    {
        if (Schema::hasTable('review_focuses')) {
            $this->dropIndexIfExists('review_focuses', 'review_focuses_workspace_key_unique');
            Schema::table('review_focuses', function (Blueprint $table) {
                $table->unique('focus_key', 'review_focuses_focus_key_unique');
            });
        }

        if (Schema::hasTable('priority_opportunity_options')) {
            $this->dropIndexIfExists('priority_opportunity_options', 'priority_option_workspace_group_key_unique');
            Schema::table('priority_opportunity_options', function (Blueprint $table) {
                $table->unique(['option_group', 'option_key'], 'priority_option_group_key_unique');
            });
        }

        if (Schema::hasTable('crawler_settings')) {
            $this->dropIndexIfExists('crawler_settings', 'crawler_settings_workspace_key_unique');
            Schema::table('crawler_settings', function (Blueprint $table) {
                $table->unique('setting_key', 'crawler_settings_setting_key_unique');
            });
        }

        if (Schema::hasTable('sls_settings')) {
            $this->dropIndexIfExists('sls_settings', 'sls_settings_workspace_key_unique');
            Schema::table('sls_settings', function (Blueprint $table) {
                $table->unique('setting_key', 'sls_settings_setting_key_unique');
            });
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $schema) use ($index) {
                $schema->dropIndex($index);
            });
        } catch (Throwable) {
            // Index names differ slightly across local/prod MySQL versions; ignore missing ones.
        }
    }
};
