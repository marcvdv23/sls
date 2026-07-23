<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAnswerLog extends Model
{
    protected $fillable = [
        'product_id',
        'ai_provider',
        'answer_language',
        'question',
        'answer',
        'retrieval_terms',
        'source_matches',
        'visual_matches',
    ];

    protected $casts = [
        'retrieval_terms' => 'array',
        'source_matches' => 'array',
        'visual_matches' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
