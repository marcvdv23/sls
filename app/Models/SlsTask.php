<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlsTask extends Model
{
    protected $fillable = [
        'title',
        'notes',
        'task_type',
        'status',
        'priority',
        'product_focus',
        'country_iso',
        'market_organization_id',
        'country_update_id',
        'related_url',
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

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class, 'country_update_id');
    }
}
