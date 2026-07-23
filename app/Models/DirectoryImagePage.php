<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectoryImagePage extends Model
{
    protected $fillable = [
        'directory_image_batch_id',
        'original_filename',
        'storage_path',
        'sort_order',
        'ocr_text',
        'status',
        'last_error',
    ];

    public function batch()
    {
        return $this->belongsTo(DirectoryImageBatch::class, 'directory_image_batch_id');
    }

    public function entries()
    {
        return $this->hasMany(DirectoryImageEntry::class);
    }
}
