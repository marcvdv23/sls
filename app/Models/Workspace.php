<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workspace extends Model
{
    protected $fillable = [
        'workspace_key',
        'entity_key',
        'entity_name',
        'name',
        'description',
        'domain_label',
        'status',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    public function memberships()
    {
        return $this->hasMany(WorkspaceUserMembership::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'workspace_user_memberships')
            ->withPivot(['role', 'status', 'metadata'])
            ->withTimestamps();
    }
}
