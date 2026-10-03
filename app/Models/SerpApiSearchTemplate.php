<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerpApiSearchTemplate extends Model
{
    protected $table = 'serpapi_search_templates';

    protected $fillable = [
        'name',
        'focus',
        'query_template',
        'keywords',
        'required_terms',
        'blocked_domains',
        'blocked_path_terms',
        'vendor_terms',
        'results_per_country',
        'is_enabled',
    ];

    protected $casts = [
        'keywords' => 'array',
        'required_terms' => 'array',
        'blocked_domains' => 'array',
        'blocked_path_terms' => 'array',
        'vendor_terms' => 'array',
        'results_per_country' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
