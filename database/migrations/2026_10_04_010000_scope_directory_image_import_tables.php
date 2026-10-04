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

        $defaultWorkspaceId = $this->defaultWorkspaceId();

        foreach ($this->tables() as $table => $afterColumn) {
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
        }

        if (Schema::hasTable('directory_image_batches') && Schema::hasColumn('directory_image_batches', 'workspace_id')) {
            DB::table('directory_image_batches')
                ->leftJoin('market_crawlers', 'directory_image_batches.market_crawler_id', '=', 'market_crawlers.id')
                ->whereNull('directory_image_batches.workspace_id')
                ->update([
                    'directory_image_batches.workspace_id' => DB::raw('COALESCE(market_crawlers.workspace_id, ' . (int) $defaultWorkspaceId . ')'),
                ]);
        }

        if (Schema::hasTable('directory_image_pages') && Schema::hasColumn('directory_image_pages', 'workspace_id')) {
            DB::table('directory_image_pages')
                ->join('directory_image_batches', 'directory_image_pages.directory_image_batch_id', '=', 'directory_image_batches.id')
                ->whereNull('directory_image_pages.workspace_id')
                ->update([
                    'directory_image_pages.workspace_id' => DB::raw('directory_image_batches.workspace_id'),
                ]);
        }

        if (Schema::hasTable('directory_image_entries') && Schema::hasColumn('directory_image_entries', 'workspace_id')) {
            DB::table('directory_image_entries')
                ->join('directory_image_batches', 'directory_image_entries.directory_image_batch_id', '=', 'directory_image_batches.id')
                ->whereNull('directory_image_entries.workspace_id')
                ->update([
                    'directory_image_entries.workspace_id' => DB::raw('directory_image_batches.workspace_id'),
                ]);
        }

        foreach (array_keys($this->tables()) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            $this->dropIndexIfExists($table, $table . '_workspace_lookup_idx');
            Schema::table($table, function (Blueprint $schema) use ($table) {
                $schema->index('workspace_id', $table . '_workspace_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys($this->tables())) as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            $this->dropIndexIfExists($table, $table . '_workspace_lookup_idx');

            Schema::table($table, function (Blueprint $schema) {
                $schema->dropConstrainedForeignId('workspace_id');
            });
        }
    }

    /**
     * @return array<string, string>
     */
    private function tables(): array
    {
        return [
            'directory_image_batches' => 'id',
            'directory_image_pages' => 'id',
            'directory_image_entries' => 'id',
        ];
    }

    private function defaultWorkspaceId(): int
    {
        $id = DB::table('workspaces')->where('is_default', true)->orderBy('id')->value('id')
            ?: DB::table('workspaces')->where('workspace_key', 'social_security')->orderBy('id')->value('id')
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
            'metadata' => json_encode(['source' => 'directory_image_scope_migration']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
