<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroupPermission extends Model
{
    protected $fillable = [
        'user_group_id',
        'form_key',
        'can_view',
        'can_search',
        'can_insert',
        'can_update',
        'can_delete',
        'can_approve',
        'can_print',
        'can_export',
        'can_import',
        'can_run_process',
        'can_assign',
        'can_configure',
    ];

    protected $casts = [
        'can_view' => 'boolean',
        'can_search' => 'boolean',
        'can_insert' => 'boolean',
        'can_update' => 'boolean',
        'can_delete' => 'boolean',
        'can_approve' => 'boolean',
        'can_print' => 'boolean',
        'can_export' => 'boolean',
        'can_import' => 'boolean',
        'can_run_process' => 'boolean',
        'can_assign' => 'boolean',
        'can_configure' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function form()
    {
        return $this->belongsTo(PermissionForm::class, 'form_key', 'key');
    }
}
