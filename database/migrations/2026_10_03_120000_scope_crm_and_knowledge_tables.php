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
            return;
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
        $this->addLookupIndexes();
    }

    public function down(): void
    {
        $this->dropWorkspaceIndexes();
        $this->restoreUniqueIndexes();

        foreach (array_reverse(array_keys($this->scopedTables())) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $schema) {
                $schema->dropConstrainedForeignId('workspace_id');
            });
        }
    }

    private function defaultWorkspaceId(): int
    {
        $id = DB::table('workspaces')->where('is_default', true)->orderBy('id')->value('id')
            ?: DB::table('workspaces')->orderBy('id')->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('workspaces')->insertGetId([
            'workspace_key' => config('sls.workspace.key', 'social_security'),
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
            'market_crawlers' => 'id',
            'market_organizations' => 'id',
            'market_organization_contacts' => 'id',
            'market_organization_activities' => 'id',
            'market_organization_tasks' => 'id',
            'market_organization_communications' => 'id',
            'market_email_accounts' => 'id',
            'market_crawler_runs' => 'id',
            'source_documents' => 'id',
            'knowledge_chunks' => 'id',
            'chat_answer_logs' => 'id',
            'intelligence_source_audits' => 'id',
        ];
    }

    private function replaceUniqueIndexes(): void
    {
        if (Schema::hasTable('market_crawlers')) {
            $this->dropIndexIfExists('market_crawlers', 'market_crawlers_name_unique');
            $this->dropIndexIfExists('market_crawlers', 'market_crawlers_crawler_key_unique');
            $this->dropIndexIfExists('market_crawlers', 'market_crawlers_workspace_name_unique');
            $this->dropIndexIfExists('market_crawlers', 'market_crawlers_workspace_key_unique');
            Schema::table('market_crawlers', function (Blueprint $table) {
                $table->unique(['workspace_id', 'name'], 'market_crawlers_workspace_name_unique');
                $table->unique(['workspace_id', 'crawler_key'], 'market_crawlers_workspace_key_unique');
            });
        }

        if (Schema::hasTable('market_organizations')) {
            $this->dropIndexIfExists('market_organizations', 'market_organizations_source_fingerprint_unique');
            $this->dropIndexIfExists('market_organizations', 'market_orgs_workspace_fingerprint_unique');
            Schema::table('market_organizations', function (Blueprint $table) {
                $table->unique(['workspace_id', 'source_fingerprint'], 'market_orgs_workspace_fingerprint_unique');
            });
        }

        if (Schema::hasTable('market_organization_contacts')) {
            $this->dropIndexIfExists('market_organization_contacts', 'market_organization_contacts_source_fingerprint_unique');
            $this->dropIndexIfExists('market_organization_contacts', 'market_contacts_workspace_fingerprint_unique');
            Schema::table('market_organization_contacts', function (Blueprint $table) {
                $table->unique(['workspace_id', 'source_fingerprint'], 'market_contacts_workspace_fingerprint_unique');
            });
        }
    }

    private function restoreUniqueIndexes(): void
    {
        if (Schema::hasTable('market_organization_contacts')) {
            $this->dropIndexIfExists('market_organization_contacts', 'market_contacts_workspace_fingerprint_unique');
            Schema::table('market_organization_contacts', function (Blueprint $table) {
                $table->unique('source_fingerprint', 'market_organization_contacts_source_fingerprint_unique');
            });
        }

        if (Schema::hasTable('market_organizations')) {
            $this->dropIndexIfExists('market_organizations', 'market_orgs_workspace_fingerprint_unique');
            Schema::table('market_organizations', function (Blueprint $table) {
                $table->unique('source_fingerprint', 'market_organizations_source_fingerprint_unique');
            });
        }

        if (Schema::hasTable('market_crawlers')) {
            $this->dropIndexIfExists('market_crawlers', 'market_crawlers_workspace_name_unique');
            $this->dropIndexIfExists('market_crawlers', 'market_crawlers_workspace_key_unique');
            Schema::table('market_crawlers', function (Blueprint $table) {
                $table->unique('name', 'market_crawlers_name_unique');
                $table->unique('crawler_key', 'market_crawlers_crawler_key_unique');
            });
        }
    }

    private function addLookupIndexes(): void
    {
        $indexes = [
            'market_organization_activities' => ['workspace_id', 'activity_at'],
            'market_organization_tasks' => ['workspace_id', 'status', 'due_at'],
            'market_crawler_runs' => ['workspace_id', 'started_at'],
            'source_documents' => ['workspace_id', 'source_type'],
            'knowledge_chunks' => ['workspace_id', 'approval_status'],
            'chat_answer_logs' => ['workspace_id', 'created_at'],
            'intelligence_source_audits' => ['workspace_id', 'checked_at'],
        ];

        foreach ($indexes as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->dropIndexIfExists($table, $table . '_workspace_lookup_idx');

            Schema::table($table, function (Blueprint $schema) use ($table, $columns) {
                $schema->index($columns, $table . '_workspace_lookup_idx');
            });
        }
    }

    private function dropWorkspaceIndexes(): void
    {
        foreach ([
            'market_organization_activities',
            'market_organization_tasks',
            'market_crawler_runs',
            'source_documents',
            'knowledge_chunks',
            'chat_answer_logs',
            'intelligence_source_audits',
        ] as $table) {
            $this->dropIndexIfExists($table, $table . '_workspace_lookup_idx');
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $schema) use ($index) {
                $schema->dropIndex($index);
            });
        } catch (Throwable) {
            // Ignore missing index names across MySQL versions/environments.
        }
    }
};
