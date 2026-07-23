<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryUpdateOrganization extends Model
{
    protected $fillable = [
        'country_update_id',
        'market_organization_id',
        'context',
    ];

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }
}
