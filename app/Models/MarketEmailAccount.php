<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketEmailAccount extends Model
{
    protected $fillable = [
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
