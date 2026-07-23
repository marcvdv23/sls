<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketCrawler extends Model
{
    protected $fillable = [
        'name',
        'crawler_key',
        'crawler_type',
        'description',
        'is_enabled',
        'last_run_at',
        'last_error',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function organizations()
    {
        return $this->hasMany(MarketOrganization::class);
    }

    public function runs()
    {
        return $this->hasMany(MarketCrawlerRun::class);
    }
}
