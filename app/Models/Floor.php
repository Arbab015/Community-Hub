<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Floor extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

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
