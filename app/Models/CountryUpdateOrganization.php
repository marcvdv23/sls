<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CountryUpdateOrganization extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
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
