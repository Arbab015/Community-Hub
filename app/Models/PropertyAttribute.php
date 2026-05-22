<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyAttribute extends Model
{
    protected $guarded = [];

    public static function findorfail(string $string) {}

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
