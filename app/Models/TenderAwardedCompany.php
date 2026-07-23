<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenderAwardedCompany extends Model
{
    protected $fillable = [
        'country_update_id',
        'market_organization_id',
        'product_id',
        'country_id',
        'company_name',
        'country_name',
        'country_iso',
        'email',
        'phone',
        'website_url',
        'contract_title',
        'contract_reference',
        'contract_value',
        'currency',
        'award_date',
        'award_url',
        'source_name',
        'contract_info',
        'relationship_status',
        'notes',
        'source_fingerprint',
    ];

    protected $casts = [
        'award_date' => 'date',
    ];

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}