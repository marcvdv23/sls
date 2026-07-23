<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoFrame extends Model
{
    protected $fillable = [
        'demo_session_id',
        'timestamp_ms',
        'image_storage_path',
        'ocr_text',
        'cleaned_ui_text',
        'detected_ui_terms',
        'ui_structure',
        'screen_summary',
        'review_status',
    ];

    protected $casts = [
        'detected_ui_terms' => 'array',
        'ui_structure' => 'array',
    ];

    public function demoSession(): BelongsTo
    {
        return $this->belongsTo(DemoSession::class);
    }
}
