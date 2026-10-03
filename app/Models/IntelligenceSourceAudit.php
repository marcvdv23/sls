<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class IntelligenceSourceAudit extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'intelligence_source_id',
        'source_name',
        'domain',
        'focus',
        'method',
        'request_url',
        'request_payload',
        'http_status',
        'ok',
        'items_found',
        'response_excerpt',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'ok' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(IntelligenceSource::class, 'intelligence_source_id');
    }
}
