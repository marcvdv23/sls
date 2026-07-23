<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UniversitySurveyContact extends Model
{
    protected $fillable = [
        'university_survey_target_id',
        'role_category',
        'person_name',
        'job_title',
        'email',
        'email_status',
        'organization',
        'source_url',
        'context_excerpt',
        'confidence_score',
        'source_fingerprint',
        'found_at',
    ];

    protected $casts = [
        'found_at' => 'datetime',
        'confidence_score' => 'decimal:2',
    ];

    public function target()
    {
        return $this->belongsTo(UniversitySurveyTarget::class, 'university_survey_target_id');
    }
}
