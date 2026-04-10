<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Floor extends Model
{
    protected $guarded = [];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function units()
    {
        return $this->hasMany(PropertyUnit::class, 'floor_id');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'floor_id')->whereNull('unit_id');
    }

    public function dimensions()
    {
        return $this->morphMany(Dimension::class, 'dimensionable');
    }
}
