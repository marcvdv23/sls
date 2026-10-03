<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (! static::workspaceColumnReady()) {
                return;
            }

            $workspaceId = WorkspaceContext::currentWorkspaceId();

            if ($workspaceId !== null) {
                $builder->where($builder->getModel()->getTable() . '.workspace_id', $workspaceId);
            }
        });

        static::creating(function ($model) {
            if (! static::workspaceColumnReady() || filled($model->workspace_id ?? null)) {
                return;
            }

            $model->workspace_id = WorkspaceContext::currentWorkspaceId();
        });
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function scopeForWorkspace(Builder $query, ?int $workspaceId): Builder
    {
        return $query
            ->withoutGlobalScope('workspace')
            ->when($workspaceId, fn (Builder $builder) => $builder->where($builder->getModel()->getTable() . '.workspace_id', $workspaceId));
    }

    protected static function workspaceColumnReady(): bool
    {
        try {
            $instance = new static();

            return Schema::hasColumn($instance->getTable(), 'workspace_id');
        } catch (\Throwable) {
            return false;
        }
    }
}
