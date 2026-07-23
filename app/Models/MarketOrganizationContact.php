<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketOrganizationContact extends Model
{
    protected $fillable = [
        'market_organization_id',
        'market_crawler_id',
        'contact_type',
        'person_name',
        'job_title',
        'email',
        'phone',
        'notes',
        'source_url',
        'context_excerpt',
        'verification_status',
        'extracted_at',
        'source_fingerprint',
    ];

    protected $casts = [
        'extracted_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }

    public function crawler()
    {
        return $this->belongsTo(MarketCrawler::class, 'market_crawler_id');
    }
}
