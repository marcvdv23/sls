<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceUserMembership extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'role',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
