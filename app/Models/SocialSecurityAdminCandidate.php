<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialSecurityAdminCandidate extends Model
{
    protected $fillable = [
        'source_document_id',
        'country_id',
        'country_iso',
        'country_name',
        'organization_name',
        'role_in_programme',
        'related_programmes',
        'evidence_excerpt',
        'confidence_score',
        'status',
        'market_organization_id',
    ];

    public function sourceDocument()
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function marketOrganization()
    {
        return $this->belongsTo(MarketOrganization::class);
    }

    public function contexts()
    {
        return $this->hasMany(SocialSecurityAdminCandidateContext::class);
    }
}
