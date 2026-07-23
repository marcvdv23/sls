<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoTranscriptSegment extends Model
{
    protected $fillable = [
        'demo_session_id',
        'start_ms',
        'end_ms',
        'speaker_label',
        'transcript_text',
    ];

    public function demoSession(): BelongsTo
    {
        return $this->belongsTo(DemoSession::class);
    }
}
