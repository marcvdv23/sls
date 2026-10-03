<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class SlsSetting extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'setting_key',
        'setting_value',
        'value_type',
        'setting_group',
        'label',
        'description',
    ];
}
