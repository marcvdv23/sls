<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketOrganizationActivity extends Model
{
    protected $fillable = [
        'market_organization_id',
        'activity_type',
        'subject',
        'body',
        'activity_at',
        'logged_by',
    ];

    protected $casts = [
        'activity_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }
}
