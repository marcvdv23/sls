<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryImageBatch extends Model
{
    protected $fillable = [
        'title',
        'organization_type',
        'industry',
        'organization_subcategory',
        'market_crawler_id',
        'status',
        'image_count',
        'entry_count',
        'notes',
    ];

    public function pages()
    {
        return $this->hasMany(DirectoryImagePage::class);
    }

    public function entries()
    {
        return $this->hasMany(DirectoryImageEntry::class);
    }

    public function crawler()
    {
        return $this->belongsTo(MarketCrawler::class, 'market_crawler_id');
    }
}
