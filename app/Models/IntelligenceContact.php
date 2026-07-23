<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceContact extends Model
{
    protected $fillable = [
        'country_update_id',
        'intelligence_document_id',
        'source_document_id',
        'country_id',
        'source_name',
        'source_url',
        'document_url',
        'document_title',
        'organization',
        'person_name',
        'job_title',
        'relationship_type',
        'contact_status',
        'email',
        'context_excerpt',
        'source_fingerprint',
        'extracted_at',
    ];

    protected $casts = [
        'extracted_at' => 'datetime',
    ];

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }

    public function intelligenceDocument()
    {
        return $this->belongsTo(IntelligenceDocument::class);
    }

    public function sourceDocument()
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
