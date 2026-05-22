<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyUnit extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function floor()
    {
        return $this->belongsTo(Floor::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'unit_id');
    }

    public function dimensions()
    {
        return $this->morphMany(Dimension::class, 'dimensionable');
    }
}
