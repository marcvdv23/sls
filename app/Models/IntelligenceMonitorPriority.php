<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceMonitorPriority extends Model
{
    protected $fillable = [
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
