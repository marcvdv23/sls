<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_admin',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_admin' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->hasMany(UserGroupPermission::class);
    }

    public function countryAccess()
    {
        return $this->hasMany(UserGroupCountryAccess::class);
    }

    public function productAccess()
    {
        return $this->hasMany(UserGroupProductAccess::class);
    }
}
