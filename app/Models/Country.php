<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso_code',
        'region',
        'default_language_code',
        'social_security_administration_name',
        'social_protection_profile_url',
        'social_protection_profile_checked_at',
        'social_protection_profile_last_success_at',
        'social_protection_profile_last_error',
        'profile_status',
    ];

    protected $casts = [
        'social_protection_profile_checked_at' => 'datetime',
        'social_protection_profile_last_success_at' => 'datetime',
    ];

    public function topics()
    {
        return $this->hasMany(CountryTopic::class);
    }

    public function updates()
    {
        return $this->hasMany(CountryUpdate::class);
    }

    public function monitorRuns()
    {
        return $this->hasMany(CountryMonitorRun::class);
    }
}
