<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGroupProductAccess extends Model
{
    protected $table = 'user_group_product_access';

    protected $fillable = [
        'user_group_id',
        'product_id',
        'product_key',
        'can_access',
    ];

    protected $casts = [
        'can_access' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
