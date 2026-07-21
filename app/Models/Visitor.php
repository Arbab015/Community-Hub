<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function favourites()
    {
        return $this->hasMany(VisitorFavourite::class);
    }
}
