<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoFeatureMoment extends Model
{
    protected $fillable = [
        'demo_session_id',
        'product_id',
        'feature_name',
        'business_problem',
        'module_name',
        'start_ms',
        'end_ms',
        'summary',
        'benefits',
        'frame_ids',
        'approval_status',
    ];

    protected $casts = [
        'benefits' => 'array',
        'frame_ids' => 'array',
    ];

    public function demoSession(): BelongsTo
    {
        return $this->belongsTo(DemoSession::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productForChat(): ?Product
    {
        return $this->product ?: $this->demoSession?->product;
    }
}
