<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketOrganizationTask extends Model
{
    protected $fillable = [
        'market_organization_id',
        'title',
        'notes',
        'task_type',
        'status',
        'due_at',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }
}
