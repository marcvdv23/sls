<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceKeyword extends Model
{
    protected $fillable = [
        'focus',
        'term',
        'language_code',
        'category',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
