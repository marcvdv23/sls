<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrawlerSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'value_type',
        'label',
        'description',
    ];
}
