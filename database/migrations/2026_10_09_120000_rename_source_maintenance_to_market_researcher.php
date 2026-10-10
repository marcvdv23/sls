<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permission_forms') || ! Schema::hasTable('user_groups') || ! Schema::hasTable('user_group_permissions')) {
            return;
        }

        $now = now();

        DB::table('permission_forms')->updateOrInsert(
            ['key' => 'source_maintenance'],
            [
                'label' => 'Market Research Sources',
                'category' => 'Setup',
                'description' => 'Restricted page for maintaining tracked country and organization URLs only.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $legacyGroupId = DB::table('user_groups')->where('slug', 'source-maintenance')->value('id');
        $marketResearcherGroupId = DB::table('user_groups')->where('slug', 'market-researcher')->value('id');

        if ($legacyGroupId && ! $marketResearcherGroupId) {
            DB::table('user_groups')->where('id', $legacyGroupId)->update([
                'name' => 'Market Researcher',
                'slug' => 'market-researcher',
                'description' => 'Can only view the market research source page and update organization URLs.',
                'is_system' => true,
                'is_admin' => false,
                'updated_at' => $now,
            ]);
            $marketResearcherGroupId = $legacyGroupId;
        }

        if (! $marketResearcherGroupId) {
            $marketResearcherGroupId = DB::table('user_groups')->insertGetId([
                'name' => 'Market Researcher',
                'slug' => 'market-researcher',
                'description' => 'Can only view the market research source page and update organization URLs.',
                'is_system' => true,
                'is_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($legacyGroupId && (int) $legacyGroupId !== (int) $marketResearcherGroupId) {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'user_group_id')) {
                DB::table('users')->where('user_group_id', $legacyGroupId)->update(['user_group_id' => $marketResearcherGroupId]);
            }

            DB::table('user_group_permissions')->where('user_group_id', $legacyGroupId)->delete();
            DB::table('user_groups')->where('id', $legacyGroupId)->delete();
        }

        DB::table('user_groups')->where('id', $marketResearcherGroupId)->update([
            'name' => 'Market Researcher',
            'slug' => 'market-researcher',
            'description' => 'Can only view the market research source page and update organization URLs.',
            'is_system' => true,
            'is_admin' => false,
            'updated_at' => $now,
        ]);

        DB::table('user_group_permissions')->updateOrInsert(
            [
                'user_group_id' => $marketResearcherGroupId,
                'form_key' => 'source_maintenance',
            ],
            $this->permissionRow($now, ['can_view', 'can_search', 'can_update']),
        );

        DB::table('permission_forms')
            ->where('key', '<>', 'source_maintenance')
            ->pluck('key')
            ->each(function (string $formKey) use ($marketResearcherGroupId, $now): void {
                DB::table('user_group_permissions')->updateOrInsert(
                    [
                        'user_group_id' => $marketResearcherGroupId,
                        'form_key' => $formKey,
                    ],
                    $this->permissionRow($now),
                );
            });

        DB::table('user_groups')
            ->where('is_admin', true)
            ->pluck('id')
            ->each(function (int $groupId) use ($now): void {
                DB::table('user_group_permissions')->updateOrInsert(
                    [
                        'user_group_id' => $groupId,
                        'form_key' => 'source_maintenance',
                    ],
                    $this->permissionRow($now, [
                        'can_view',
                        'can_search',
                        'can_insert',
                        'can_update',
                        'can_delete',
                        'can_approve',
                        'can_print',
                        'can_export',
                        'can_import',
                        'can_run_process',
                        'can_assign',
                        'can_configure',
                    ]),
                );
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permission_forms') || ! Schema::hasTable('user_groups') || ! Schema::hasTable('user_group_permissions')) {
            return;
        }

        $now = now();

        DB::table('permission_forms')->where('key', 'source_maintenance')->update([
            'label' => 'Source Maintenance',
            'updated_at' => $now,
        ]);

        DB::table('user_groups')->where('slug', 'market-researcher')->update([
            'name' => 'Source Maintenance',
            'slug' => 'source-maintenance',
            'description' => 'Can only view the source maintenance page and update organization URLs.',
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<int, string> $enabled
     * @return array<string, mixed>
     */
    private function permissionRow($now, array $enabled = []): array
    {
        $row = [
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ([
            'can_view',
            'can_search',
            'can_insert',
            'can_update',
            'can_delete',
            'can_approve',
            'can_print',
            'can_export',
            'can_import',
            'can_run_process',
            'can_assign',
            'can_configure',
        ] as $column) {
            $row[$column] = in_array($column, $enabled, true);
        }

        return $row;
    }
};
