<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryImageEntry extends Model
{
    protected $fillable = [
        'directory_image_batch_id',
        'directory_image_page_id',
        'market_organization_id',
        'organization_name',
        'address_text',
        'country_raw',
        'country_normalized',
        'country_iso',
        'website',
        'email',
        'phone',
        'executive_text',
        'raw_text',
        'confidence',
        'review_status',
    ];

    public function batch()
    {
        return $this->belongsTo(DirectoryImageBatch::class, 'directory_image_batch_id');
    }

    public function page()
    {
        return $this->belongsTo(DirectoryImagePage::class, 'directory_image_page_id');
    }

    public function organization()
    {
        return $this->belongsTo(MarketOrganization::class, 'market_organization_id');
    }
}
