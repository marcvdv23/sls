<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlsOperationRun extends Model
{
    protected $fillable = [
        'operation_key',
        'operation_name',
        'status',
        'parameters',
        'summary',
        'items',
        'processed_count',
        'total_count',
        'success_count',
        'failure_count',
        'dry_run',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'parameters' => 'array',
        'summary' => 'array',
        'items' => 'array',
        'processed_count' => 'integer',
        'total_count' => 'integer',
        'success_count' => 'integer',
        'failure_count' => 'integer',
        'dry_run' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
