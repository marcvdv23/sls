<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionForm extends Model
{
    protected $fillable = [
        'key',
        'label',
        'category',
        'description',
    ];
}
