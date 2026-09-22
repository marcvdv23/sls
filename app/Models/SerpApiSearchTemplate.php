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
        'results_per_country',
        'is_enabled',
    ];

    protected $casts = [
        'keywords' => 'array',
        'results_per_country' => 'integer',
        'is_enabled' => 'boolean',
    ];
}
