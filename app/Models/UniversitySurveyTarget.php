<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniversitySurveyTarget extends Model
{
    protected $fillable = [
        'name',
        'country',
        'website_url',
        'website_fingerprint',
        'domain',
        'status',
        'last_crawled_at',
        'next_crawl_at',
        'pages_checked',
        'contacts_found',
        'published_email_patterns',
        'last_error',
    ];

    protected $casts = [
        'last_crawled_at' => 'datetime',
        'next_crawl_at' => 'datetime',
        'published_email_patterns' => 'array',
    ];

    public function contacts()
    {
        return $this->hasMany(UniversitySurveyContact::class);
    }
}
