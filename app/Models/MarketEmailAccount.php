<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class MarketEmailAccount extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'account_name',
        'email_address',
        'provider',
        'sync_status',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
