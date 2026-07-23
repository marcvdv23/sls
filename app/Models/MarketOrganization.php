<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketOrganization extends Model
{
    protected $fillable = [
        'market_crawler_id',
        'product_id',
        'name',
        'name_normalized',
        'organization_type',
        'industry',
        'organization_subcategory',
        'country',
        'country_raw',
        'country_iso',
        'country_resolution_status',
        'region',
        'website_url',
        'website_domain',
        'organization_phone',
        'student_count',
        'procurement_page_url',
        'leadership_page_url',
        'hr_page_url',
        'it_page_url',
        'news_page_url',
        'status',
        'lead_status',
        'lead_source',
        'last_crawled_at',
        'last_crawler_name',
        'next_crawl_at',
        'last_contacted_at',
        'last_error',
        'notes',
        'source_fingerprint',
    ];

    protected $casts = [
        'last_crawled_at' => 'datetime',
        'next_crawl_at' => 'datetime',
        'last_contacted_at' => 'datetime',
    ];

    public function crawler()
    {
        return $this->belongsTo(MarketCrawler::class, 'market_crawler_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function contacts()
    {
        return $this->hasMany(MarketOrganizationContact::class);
    }

    public function activities()
    {
        return $this->hasMany(MarketOrganizationActivity::class);
    }

    public function tasks()
    {
        return $this->hasMany(MarketOrganizationTask::class);
    }

    public function communications()
    {
        return $this->hasMany(MarketOrganizationCommunication::class);
    }

    public function crawlerRuns()
    {
        return $this->hasMany(MarketCrawlerRun::class);
    }

    public function awardedContracts()
    {
        return $this->hasMany(TenderAwardedCompany::class);
    }
}
