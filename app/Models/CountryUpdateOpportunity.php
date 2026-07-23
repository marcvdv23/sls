<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryUpdateOpportunity extends Model
{
    protected $fillable = [
        'country_update_id',
        'product_id',
        'issue_area',
        'opportunity_stage',
        'issue_summary',
        'product_alignment',
        'suggested_email',
        'knowledge_chunk_ids',
        'demo_feature_moment_ids',
        'demo_frame_ids',
    ];

    protected $casts = [
        'knowledge_chunk_ids' => 'array',
        'demo_feature_moment_ids' => 'array',
        'demo_frame_ids' => 'array',
    ];

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
