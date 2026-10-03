<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewFocus extends Model
{
    protected $fillable = [
        'focus_key',
        'label',
        'description',
        'terms',
        'strong_signals',
        'metadata',
        'sort_order',
        'is_enabled',
        'is_default',
    ];

    protected $casts = [
        'terms' => 'array',
        'strong_signals' => 'array',
        'metadata' => 'array',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
        'is_default' => 'boolean',
    ];
}
