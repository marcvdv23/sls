<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class OutOfOfficeReferral extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'mailbox',
        'message_uid',
        'message_id',
        'message_date',
        'subject',
        'from_name',
        'from_email',
        'referred_name',
        'referred_email',
        'referred_job_title',
        'referred_organization',
        'context_excerpt',
        'raw_excerpt',
        'status',
        'source_fingerprint',
    ];

    protected $casts = [
        'message_date' => 'datetime',
    ];
}
