<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class MarketOrganizationActivity extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'market_organization_id',
        'activity_type',
        'subject',
        'body',
        'activity_at',
        'logged_by',
    ];

    protected $casts = [
        'activity_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }
}
