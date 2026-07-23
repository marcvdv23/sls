<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryTopic extends Model
{
    protected $fillable = [
        'country_id',
        'topic',
        'tracking_status',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }
}
