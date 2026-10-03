<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class MarketOrganizationCommunication extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'market_organization_id',
        'market_organization_contact_id',
        'channel',
        'direction',
        'subject',
        'body_excerpt',
        'from_address',
        'to_address',
        'sent_or_received_at',
        'source_reference',
    ];

    protected $casts = [
        'sent_or_received_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }

    public function contact()
    {
        return $this->belongsTo(MarketOrganizationContact::class, 'market_organization_contact_id');
    }
}
