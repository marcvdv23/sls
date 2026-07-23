<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journalist extends Model
{
    protected $fillable = [
        'name',
        'name_normalized',
        'email',
        'publication_name',
        'publication_country_id',
        'publication_country_name',
        'topics',
        'source_fingerprint',
        'discovery_status',
        'profile_summary',
        'background',
        'education',
        'profile_sources',
        'notes',
        'last_seen_at',
    ];

    protected $casts = [
        'topics' => 'array',
        'profile_sources' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function publicationCountry()
    {
        return $this->belongsTo(Country::class, 'publication_country_id');
    }

    public function articles()
    {
        return $this->hasMany(JournalistArticle::class);
    }
}
