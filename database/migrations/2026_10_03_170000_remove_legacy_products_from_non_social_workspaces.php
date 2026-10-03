<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces')) {
            return;
        }

        $socialWorkspaceIds = DB::table('workspaces')
            ->where('workspace_key', 'social_security')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($socialWorkspaceIds === []) {
            return;
        }

        $nonSocialWorkspaceIds = DB::table('workspaces')
            ->whereNotIn('id', $socialWorkspaceIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($nonSocialWorkspaceIds === []) {
            return;
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'workspace_id')) {
            DB::table('products')
                ->whereIn('workspace_id', $nonSocialWorkspaceIds)
                ->whereRaw('UPPER(code) IN (?, ?, ?, ?)', ['SSAS', 'HRMS', 'ERMS', 'EBPC'])
                ->delete();
        }

        if (Schema::hasTable('sls_settings') && Schema::hasColumn('sls_settings', 'workspace_id')) {
            DB::table('sls_settings')
                ->whereIn('workspace_id', $nonSocialWorkspaceIds)
                ->where('setting_key', 'products.order')
                ->where(function ($query) {
                    $query->where('setting_value', 'like', '%SSAS%')
                        ->orWhere('setting_value', 'like', '%HRMS%')
                        ->orWhere('setting_value', 'like', '%ERMS%')
                        ->orWhere('setting_value', 'like', '%EBPC%');
                })
                ->update([
                    'setting_value' => '',
                    'updated_at' => now(),
                ]);

            DB::table('sls_settings')
                ->whereIn('workspace_id', $nonSocialWorkspaceIds)
                ->where('setting_key', 'products.focus_map')
                ->where(function ($query) {
                    $query->where('setting_value', 'like', '%social_security%')
                        ->orWhere('setting_value', 'like', '%hrms_tenders%')
                        ->orWhere('setting_value', 'like', '%erms_tenders%')
                        ->orWhere('setting_value', 'like', '%ebpc_tenders%');
                })
                ->update([
                    'setting_value' => '',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally not restored. Non-social workspaces should keep their own product catalog.
    }
};
