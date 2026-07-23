<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoSession extends Model
{
    protected $fillable = [
        'product_id',
        'title',
        'source_type',
        'language_code',
        'video_storage_path',
        'transcript_storage_path',
        'duration_seconds',
        'processing_status',
        'processing_notes',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function frames(): HasMany
    {
        return $this->hasMany(DemoFrame::class);
    }

    public function transcriptSegments(): HasMany
    {
        return $this->hasMany(DemoTranscriptSegment::class);
    }

    public function featureMoments(): HasMany
    {
        return $this->hasMany(DemoFeatureMoment::class);
    }
}
