<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class IntelligenceMonitorPriority extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'country_iso',
        'country_name',
        'focus',
        'status',
        'requested_at',
        'used_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
