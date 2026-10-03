<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class PriorityOpportunityOption extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
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
