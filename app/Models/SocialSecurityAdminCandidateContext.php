<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialSecurityAdminCandidateContext extends Model
{
    protected $fillable = [
        'social_security_admin_candidate_id',
        'context_key',
        'role_in_programme',
        'related_programmes',
        'normalized_roles',
        'programme_l1',
        'programme_l2',
        'employer_types',
        'special_system_mentioned',
        'special_system_employer_types',
        'needs_enrichment',
        'classification_notes',
        'evidence_excerpt',
        'confidence_score',
    ];

    protected $casts = [
        'normalized_roles' => 'array',
        'programme_l1' => 'array',
        'programme_l2' => 'array',
        'employer_types' => 'array',
        'special_system_mentioned' => 'boolean',
        'special_system_employer_types' => 'array',
        'needs_enrichment' => 'boolean',
    ];

    public function candidate()
    {
        return $this->belongsTo(SocialSecurityAdminCandidate::class, 'social_security_admin_candidate_id');
    }
}
