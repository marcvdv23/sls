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

        if (Schema::hasTable('priority_opportunities')) {
            $this->dropIndexIfExists('priority_opportunities', 'priority_opportunities_source_fingerprint_unique');
            $this->dropIndexIfExists('priority_opportunities', 'priority_opportunities_workspace_fingerprint_unique');

            Schema::table('priority_opportunities', function (Blueprint $table) {
                $table->unique(['workspace_id', 'source_fingerprint'], 'priority_opportunities_workspace_fingerprint_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('priority_opportunities')) {
            $this->dropIndexIfExists('priority_opportunities', 'priority_opportunities_workspace_fingerprint_unique');

            Schema::table('priority_opportunities', function (Blueprint $table) {
                $table->unique('source_fingerprint', 'priority_opportunities_source_fingerprint_unique');
            });
        }

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
            'priority_opportunities' => 'id',
            'sls_tasks' => 'id',
        ];
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        try {
            Schema::table($table, function (Blueprint $schema) use ($index) {
                $schema->dropIndex($index);
            });
        } catch (Throwable) {
            // Ignore missing index names across MySQL versions/environments.
        }
    }
};
