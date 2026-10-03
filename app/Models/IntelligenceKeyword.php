<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class IntelligenceKeyword extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
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
