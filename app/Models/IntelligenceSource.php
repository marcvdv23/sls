<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceSource extends Model
{
    protected $fillable = [
        'country_iso',
        'region',
        'name',
        'domain',
        'url',
        'source_class',
        'procurement_portal_type',
        'focus',
        'access_method',
        'connector',
        'registration_status',
        'registration_notes',
        'is_enabled',
        'last_checked_at',
        'last_success_at',
        'last_error',
        'force_next_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_success_at' => 'datetime',
        'force_next_at' => 'datetime',
    ];
}
