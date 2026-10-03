<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriorityOpportunityOption extends Model
{
    protected $fillable = [
        'option_group',
        'option_key',
        'label',
        'description',
        'metadata',
        'sort_order',
        'is_enabled',
        'is_default',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
    ];
}
