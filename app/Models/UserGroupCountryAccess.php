<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroupCountryAccess extends Model
{
    protected $table = 'user_group_country_access';

    protected $fillable = [
        'user_group_id',
        'country_id',
        'region',
        'can_access',
    ];

    protected $casts = [
        'can_access' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
