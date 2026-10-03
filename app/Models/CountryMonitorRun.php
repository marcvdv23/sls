<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CountryMonitorRun extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'country_id',
        'focus',
        'started_at',
        'finished_at',
        'sources_checked',
        'items_found',
        'status',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'sources_checked' => 'array',
        'items_found' => 'integer',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
