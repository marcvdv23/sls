<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkspaceContext
{
    public const SESSION_KEY = 'sls.current_workspace_id';

    protected static ?Workspace $currentWorkspace = null;
    protected static ?bool $tableReady = null;

    public static function current(): ?Workspace
    {
        if (! static::tableReady()) {
            return null;
        }

        if (static::$currentWorkspace) {
            return static::$currentWorkspace;
        }

        $selectedWorkspaceId = static::selectedWorkspaceId();

        if ($selectedWorkspaceId) {
            $selectedWorkspace = static::scopeToUserMemberships(Workspace::query())
                ->whereKey($selectedWorkspaceId)
                ->where('status', 'active')
                ->first();

            if ($selectedWorkspace) {
                return static::$currentWorkspace = $selectedWorkspace;
            }

            static::clearSelectedWorkspace();
        }

        return static::$currentWorkspace = static::scopeToUserMemberships(Workspace::query())
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?: static::scopeToUserMemberships(Workspace::query())->orderBy('id')->first();
    }

    public static function currentWorkspaceId(): ?int
    {
        return static::current()?->id;
    }

    public static function forceWorkspace(?int $workspaceId): ?Workspace
    {
        static::flush();

        if (! $workspaceId || ! static::tableReady()) {
            return null;
        }

        return static::$currentWorkspace = Workspace::query()
            ->whereKey($workspaceId)
            ->where('status', 'active')
            ->first();
    }

    public static function selectableWorkspaces(): Collection
    {
        if (! static::tableReady()) {
            return collect();
        }

        return static::scopeToUserMemberships(Workspace::query())
            ->where('status', 'active')
            ->orderBy('entity_name')
            ->orderBy('name')
            ->get();
    }

    public static function selectWorkspace(int $workspaceId): ?Workspace
    {
        if (! static::tableReady()) {
            return null;
        }

        $workspace = static::scopeToUserMemberships(Workspace::query())
            ->whereKey($workspaceId)
            ->where('status', 'active')
            ->first();

        if (! $workspace) {
            return null;
        }

        static::storeSelectedWorkspaceId($workspace->id);
        static::flush();

        return $workspace;
    }

    public static function seedDefaultWorkspace(): ?Workspace
    {
        if (! static::tableReady()) {
            return null;
        }

        $existingDefault = Workspace::query()->where('is_default', true)->orderBy('id')->first();

        if ($existingDefault) {
            return $existingDefault;
        }

        $workspace = Workspace::query()->firstOrCreate(
            ['workspace_key' => config('sls.workspace.key', 'social_security')],
            [
                'entity_key' => config('sls.entity.key', '2interact'),
                'entity_name' => config('sls.entity.display_name', config('sls.entity.name', '2interact')),
                'name' => config('sls.workspace.name', '2Interact'),
                'description' => config('sls.workspace.description', '2Interact sales intelligence across HRMS, SSAS, EBPC, and ERMS product lines.'),
                'domain_label' => config('sls.workspace.domain_label', '2Interact Public Sector Software'),
                'status' => 'active',
                'is_default' => true,
                'metadata' => ['source' => 'default_seed'],
            ]
        );

        if (! $workspace->is_default && ! Workspace::query()->where('is_default', true)->whereKeyNot($workspace->id)->exists()) {
            $workspace->forceFill(['is_default' => true])->save();
        }

        static::flush();

        return $workspace;
    }

    public static function apply(Builder $query, ?int $workspaceId = null): Builder
    {
        $model = $query->getModel();

        try {
            if (! Schema::hasColumn($model->getTable(), 'workspace_id')) {
                return $query;
            }
        } catch (\Throwable) {
            return $query;
        }

        $workspaceId ??= static::currentWorkspaceId();

        return $workspaceId ? $query->where($model->getTable() . '.workspace_id', $workspaceId) : $query;
    }

    public static function settingValue(string $table, string $key, string $valueColumn = 'setting_value'): mixed
    {
        $query = DB::table($table)->where('setting_key', $key);

        try {
            if (Schema::hasColumn($table, 'workspace_id') && ($workspaceId = static::currentWorkspaceId())) {
                $query->where('workspace_id', $workspaceId);
            }
        } catch (\Throwable) {
            // Fall back to legacy unscoped lookup while migrations are not available.
        }

        return $query->value($valueColumn);
    }

    public static function flush(): void
    {
        static::$currentWorkspace = null;
        static::$tableReady = null;
        SlsSettings::flush();
        ReviewFocuses::flush();
        PriorityOpportunityConfig::flush();
    }

    protected static function selectedWorkspaceId(): ?int
    {
        try {
            if (app()->runningInConsole()) {
                return null;
            }

            $request = request();

            if (! $request->hasSession()) {
                return null;
            }

            $workspaceId = (int) $request->session()->get(static::SESSION_KEY);

            return $workspaceId > 0 ? $workspaceId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function storeSelectedWorkspaceId(int $workspaceId): void
    {
        try {
            if (! app()->runningInConsole() && request()->hasSession()) {
                request()->session()->put(static::SESSION_KEY, $workspaceId);
            }
        } catch (\Throwable) {
            // Console and early boot contexts do not have a writable session.
        }
    }

    protected static function clearSelectedWorkspace(): void
    {
        try {
            if (! app()->runningInConsole() && request()->hasSession()) {
                request()->session()->forget(static::SESSION_KEY);
            }
        } catch (\Throwable) {
            // Console and early boot contexts do not have a writable session.
        }
    }

    protected static function tableReady(): bool
    {
        if (static::$tableReady !== null) {
            return static::$tableReady;
        }

        try {
            static::$tableReady = Schema::hasTable('workspaces');
        } catch (\Throwable) {
            static::$tableReady = false;
        }

        return static::$tableReady;
    }

    protected static function scopeToUserMemberships(Builder $query): Builder
    {
        if (app()->runningInConsole()) {
            return $query;
        }

        try {
            if (! Schema::hasTable('workspace_user_memberships') || ! auth()->check()) {
                return $query;
            }

            $userId = (int) auth()->id();
            $workspaceIds = DB::table('workspace_user_memberships')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->pluck('workspace_id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->values();

            if ($workspaceIds->isEmpty()) {
                return $query;
            }

            return $query->whereIn('id', $workspaceIds->all());
        } catch (\Throwable) {
            return $query;
        }
    }
}
