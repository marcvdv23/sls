<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'source_document_id',
        'product_id',
        'country_id',
        'chunk_title',
        'chunk_text',
        'citation_label',
        'page_number',
        'content_type',
        'business_area',
        'answer_priority',
        'approval_status',
    ];

    public function sourceDocument()
    {
        return $this->belongsTo(SourceDocument::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
