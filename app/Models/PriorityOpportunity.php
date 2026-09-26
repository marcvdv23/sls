<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriorityOpportunity extends Model
{
    protected $fillable = [
        'country_id',
        'primary_organization_id',
        'primary_task_id',
        'country_update_id',
        'country_market',
        'country_iso',
        'region',
        'focus_tier',
        'institution',
        'reform_development',
        'stage_2026',
        'why_relevant',
        'evidence_scale',
        'donor_support',
        'evidence_confidence',
        'recommended_next_action',
        'source_1',
        'source_2',
        'origin',
        'review_notes',
        'status',
        'priority',
        'product_focus',
        'next_follow_up_at',
        'last_activity_at',
        'source_fingerprint',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function primaryOrganization()
    {
        return $this->belongsTo(MarketOrganization::class, 'primary_organization_id');
    }

    public function organizations()
    {
        return $this->belongsToMany(MarketOrganization::class)
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    public function primaryTask()
    {
        return $this->belongsTo(SlsTask::class, 'primary_task_id');
    }

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }
}
