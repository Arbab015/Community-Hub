<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
