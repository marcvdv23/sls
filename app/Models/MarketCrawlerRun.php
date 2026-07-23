<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCrawlerRun extends Model
{
    protected $fillable = [
        'market_crawler_id',
        'market_organization_id',
        'run_type',
        'status',
        'query_text',
        'request_url',
        'url_checked',
        'http_status',
        'items_found',
        'urls_updated',
        'contacts_found',
        'result_payload',
        'response_excerpt',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'result_payload' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function crawler()
    {
        return $this->belongsTo(MarketCrawler::class, 'market_crawler_id');
    }

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }
}
