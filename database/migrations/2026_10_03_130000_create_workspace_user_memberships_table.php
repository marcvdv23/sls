<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('workspaces') || ! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasTable('workspace_user_memberships')) {
            Schema::create('workspace_user_memberships', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('role', 40)->default('member')->index();
                $table->string('status', 40)->default('active')->index();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['workspace_id', 'user_id'], 'workspace_user_memberships_unique');
                $table->index(['user_id', 'status'], 'workspace_user_memberships_user_status_idx');
            });
        }

        $this->backfillMemberships();
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user_memberships');
    }

    private function backfillMemberships(): void
    {
        $workspaces = DB::table('workspaces')->select(['id', 'workspace_key'])->get();
        $users = DB::table('users')
            ->select(['id'])
            ->where(function ($query) {
                $query->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('id')
            ->get();

        if ($workspaces->isEmpty() || $users->isEmpty()) {
            return;
        }

        $firstUserId = (int) $users->first()->id;
        $now = now();

        foreach ($workspaces as $workspace) {
            foreach ($users as $user) {
                DB::table('workspace_user_memberships')->updateOrInsert(
                    [
                        'workspace_id' => (int) $workspace->id,
                        'user_id' => (int) $user->id,
                    ],
                    [
                        'role' => (int) $user->id === $firstUserId ? 'owner' : 'member',
                        'status' => 'active',
                        'metadata' => json_encode(['source' => 'migration_backfill']),
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }
    }
};
