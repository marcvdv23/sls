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
            ['key' => 'opportunities'],
            [
                'label' => 'Opportunity Drafts',
                'category' => 'CRM',
                'description' => 'Opportunity drafts created from Review Desk items.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $groupId = DB::table('user_groups')->where('slug', 'basic-reviewer')->value('id');

        if (! $groupId) {
            $groupId = DB::table('user_groups')->insertGetId([
                'name' => 'Basic Reviewer',
                'slug' => 'basic-reviewer',
                'description' => 'Can use Review Desk, crawler keywords, opportunity follow-up, and To Do only.',
                'is_system' => true,
                'is_admin' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $allowed = [
            'intelligence_review' => ['can_view', 'can_search', 'can_update', 'can_approve'],
            'intelligence_keywords' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_delete'],
            'opportunities' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_assign'],
            'organization_tasks' => ['can_view', 'can_search', 'can_insert', 'can_update', 'can_assign'],
        ];

        DB::table('permission_forms')
            ->pluck('key')
            ->each(function (string $formKey) use ($allowed, $groupId, $now): void {
                DB::table('user_group_permissions')->updateOrInsert(
                    [
                        'user_group_id' => $groupId,
                        'form_key' => $formKey,
                    ],
                    $this->permissionRow($now, $allowed[$formKey] ?? []),
                );
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_groups') || ! Schema::hasTable('user_group_permissions')) {
            return;
        }

        $groupId = DB::table('user_groups')->where('slug', 'basic-reviewer')->value('id');

        if ($groupId) {
            DB::table('user_group_permissions')->where('user_group_id', $groupId)->delete();
            DB::table('user_groups')->where('id', $groupId)->delete();
        }
    }

    /**
     * @param array<int, string> $enabled
     * @return array<string, mixed>
     */
    private function permissionRow($now, array $enabled): array
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
