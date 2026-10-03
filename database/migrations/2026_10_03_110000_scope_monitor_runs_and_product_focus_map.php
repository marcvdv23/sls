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

        if (Schema::hasTable('country_monitor_runs')) {
            Schema::table('country_monitor_runs', function (Blueprint $table) {
                $table->index(['workspace_id', 'focus', 'finished_at'], 'monitor_runs_workspace_focus_finished_idx');
            });
        }

        if (Schema::hasTable('intelligence_monitor_priorities')) {
            Schema::table('intelligence_monitor_priorities', function (Blueprint $table) {
                $table->index(['workspace_id', 'country_iso', 'focus', 'status'], 'monitor_priority_workspace_lookup');
            });
        }

        $this->seedProductFocusMaps();
    }

    public function down(): void
    {
        if (Schema::hasTable('intelligence_monitor_priorities')) {
            $this->dropIndexIfExists('intelligence_monitor_priorities', 'monitor_priority_workspace_lookup');
        }

        if (Schema::hasTable('country_monitor_runs')) {
            $this->dropIndexIfExists('country_monitor_runs', 'monitor_runs_workspace_focus_finished_idx');
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
            'country_monitor_runs' => 'id',
            'intelligence_monitor_priorities' => 'id',
        ];
    }

    private function seedProductFocusMaps(): void
    {
        if (! Schema::hasTable('sls_settings') || ! Schema::hasColumn('sls_settings', 'workspace_id')) {
            return;
        }

        $now = now();
        $maps = [
            'social_security' => "SSAS=social_security\nINTERACT-SSAS=social_security\nINTERACT SSAS=social_security\nHRMS=hrms_tenders\nINTERACT-HRMS=hrms_tenders\nINTERACT HRMS=hrms_tenders\nERMS=erms_tenders\nINTERACT-ERMS=erms_tenders\nINTERACT ERMS=erms_tenders\nEBPC=ebpc_tenders\nINTERACT-EBPC=ebpc_tenders\nINTERACT EBPC=ebpc_tenders",
            'sustainability_consulting' => "SUST=consulting_opportunity\nESG=background_intelligence\nENVPOL=policy_initiative\nCLIMATE=policy_initiative\nCLIMFIN=donor_project\nCARBON=background_intelligence\nENERGY=donor_project\nCIRCULAR=donor_project\nDONORENV=donor_project",
        ];

        foreach ($maps as $workspaceKey => $focusMap) {
            $workspaceId = DB::table('workspaces')->where('workspace_key', $workspaceKey)->value('id');

            if (! $workspaceId) {
                continue;
            }

            DB::table('sls_settings')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'setting_key' => 'products.focus_map'],
                [
                    'setting_value' => $focusMap,
                    'value_type' => 'text',
                    'setting_group' => 'Products',
                    'label' => 'Product review focus map',
                    'description' => 'One product-to-review-focus mapping per line, such as SSAS=social_security.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
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
