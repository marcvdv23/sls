<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntelligenceDocument extends Model
{
    protected $fillable = [
        'country_update_id',
        'country_id',
        'source_name',
        'source_url',
        'document_url',
        'document_title',
        'content_type',
        'storage_path',
        'sha256_hash',
        'byte_size',
        'is_pdf',
        'fetched_at',
        'extraction_notes',
    ];

    protected $casts = [
        'byte_size' => 'integer',
        'is_pdf' => 'boolean',
        'fetched_at' => 'datetime',
    ];

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function contacts()
    {
        return $this->hasMany(IntelligenceContact::class);
    }
}
