<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlsSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'value_type',
        'setting_group',
        'label',
        'description',
    ];
}
