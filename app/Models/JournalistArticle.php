<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalistArticle extends Model
{
    protected $fillable = [
        'journalist_id',
        'country_update_id',
        'article_title',
        'article_url',
        'publication_name',
        'publication_country_id',
        'topics',
        'author_name_raw',
        'author_email_raw',
        'praise_note',
        'outreach_status',
        'captured_at',
    ];

    protected $casts = [
        'topics' => 'array',
        'captured_at' => 'datetime',
    ];

    public function journalist()
    {
        return $this->belongsTo(Journalist::class);
    }

    public function countryUpdate()
    {
        return $this->belongsTo(CountryUpdate::class);
    }

    public function publicationCountry()
    {
        return $this->belongsTo(Country::class, 'publication_country_id');
    }
}
