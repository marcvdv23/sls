<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceDocument extends Model
{
    protected $fillable = [
        'title',
        'source_type',
        'intake_category',
        'intake_action',
        'contact_relationship_type',
        'related_country_id',
        'related_organization_name',
        'intake_notes',
        'language_code',
        'original_filename',
        'storage_path',
        'source_url',
        'sender',
        'recipients',
        'email_subject',
        'source_date',
        'retrieved_at',
        'approval_status',
        'approved_for_proposals',
        'owner_user_id',
    ];

    protected $casts = [
        'approved_for_proposals' => 'boolean',
        'source_date' => 'date',
        'retrieved_at' => 'datetime',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_source_documents');
    }

    public function chunks()
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function relatedCountry()
    {
        return $this->belongsTo(Country::class, 'related_country_id');
    }

    public function intakeContacts()
    {
        return $this->hasMany(IntelligenceContact::class, 'source_document_id');
    }
}
