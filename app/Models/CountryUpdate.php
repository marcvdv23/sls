<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryUpdate extends Model
{
    protected $fillable = [
        'country_id',
        'country_topic_id',
        'source_document_id',
        'title',
        'title_english',
        'title_original',
        'source_name',
        'source_url',
        'source_fingerprint',
        'publication_date',
        'retrieved_at',
        'summary',
        'summary_english',
        'relevance_score',
        'review_status',
        'rejection_reason_code',
        'rejection_reason',
        'rejected_at',
        'award_status',
        'award_checked_at',
        'award_title',
        'award_url',
        'award_source_name',
        'award_date',
        'award_context',
        'is_favorite',
        'favorited_at',
        'favorite_note',
        'reminder_due_at',
        'reminder_completed_at',
        'map_opened_at',
        'map_opened_by_user_id',
        'map_action_status',
        'map_action_note',
        'map_processed_at',
        'map_processed_by_user_id',
        'archive_read_at',
        'archive_read_by_user_id',
        'archive_note',
    ];

    protected $casts = [
        'publication_date' => 'date',
        'retrieved_at' => 'datetime',
        'relevance_score' => 'decimal:2',
        'rejected_at' => 'datetime',
        'award_checked_at' => 'datetime',
        'award_date' => 'date',
        'is_favorite' => 'boolean',
        'favorited_at' => 'datetime',
        'reminder_due_at' => 'datetime',
        'reminder_completed_at' => 'datetime',
        'map_opened_at' => 'datetime',
        'map_processed_at' => 'datetime',
        'archive_read_at' => 'datetime',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function topic()
    {
        return $this->belongsTo(CountryTopic::class, 'country_topic_id');
    }

    public function opportunities()
    {
        return $this->hasMany(CountryUpdateOpportunity::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(MarketOrganization::class, 'country_update_organizations')
            ->withPivot('context')
            ->withTimestamps();
    }

    public function journalistArticles()
    {
        return $this->hasMany(JournalistArticle::class);
    }

    public function intelligenceContacts()
    {
        return $this->hasMany(IntelligenceContact::class);
    }

    public function intelligenceDocuments()
    {
        return $this->hasMany(IntelligenceDocument::class);
    }
}



