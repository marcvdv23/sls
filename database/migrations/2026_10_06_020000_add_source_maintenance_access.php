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
                'label' => 'Source Maintenance',
                'category' => 'Setup',
                'description' => 'Restricted page for maintaining tracked country and organization URLs only.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $adminGroupIds = DB::table('user_groups')->where('is_admin', true)->pluck('id');

        foreach ($adminGroupIds as $groupId) {
            DB::table('user_group_permissions')->updateOrInsert(
                [
                    'user_group_id' => $groupId,
                    'form_key' => 'source_maintenance',
                ],
                $this->permissionRow(true, $now),
            );
        }

        $groupId = DB::table('user_groups')->where('slug', 'source-maintenance')->value('id');

        if (! $groupId) {
            $groupId = DB::table('user_groups')->insertGetId([
                'name' => 'Source Maintenance',
                'slug' => 'source-maintenance',
                'description' => 'Can only view the source maintenance page and update organization URLs.',
                'is_system' => true,
                'is_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('user_group_permissions')->updateOrInsert(
            [
                'user_group_id' => $groupId,
                'form_key' => 'source_maintenance',
            ],
            $this->permissionRow(false, $now, ['can_view', 'can_search', 'can_update']),
        );

        DB::table('permission_forms')
            ->where('key', '<>', 'source_maintenance')
            ->pluck('key')
            ->each(function (string $formKey) use ($groupId, $now): void {
                DB::table('user_group_permissions')->updateOrInsert(
                    [
                        'user_group_id' => $groupId,
                        'form_key' => $formKey,
                    ],
                    $this->permissionRow(false, $now),
                );
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('permission_forms') || ! Schema::hasTable('user_groups') || ! Schema::hasTable('user_group_permissions')) {
            return;
        }

        $groupId = DB::table('user_groups')->where('slug', 'source-maintenance')->value('id');

        if ($groupId) {
            DB::table('user_group_permissions')->where('user_group_id', $groupId)->delete();
            DB::table('user_groups')->where('id', $groupId)->delete();
        }

        DB::table('user_group_permissions')->where('form_key', 'source_maintenance')->delete();
        DB::table('permission_forms')->where('key', 'source_maintenance')->delete();
    }

    /**
     * @param array<int, string> $enabled
     * @return array<string, mixed>
     */
    private function permissionRow(bool $all, $now, array $enabled = []): array
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
            $row[$column] = $all || in_array($column, $enabled, true);
        }

        return $row;
    }
};
