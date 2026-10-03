<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkspaceContext
{
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

        return static::$currentWorkspace = Workspace::query()
            ->where('is_default', true)
            ->orderBy('id')
            ->first()
            ?: Workspace::query()->orderBy('id')->first();
    }

    public static function currentWorkspaceId(): ?int
    {
        return static::current()?->id;
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
                'name' => config('sls.workspace.name', 'Social Security Sales'),
                'description' => config('sls.workspace.description', 'Social security, pensions, public sector HR/payroll, and related sales intelligence.'),
                'domain_label' => config('sls.workspace.domain_label', 'Social Security and Public Sector Software'),
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
}
