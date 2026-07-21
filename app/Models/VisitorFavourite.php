<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorFavourite extends Model
{
    protected $guarded = [];

    public function favouritable()
    {
        return $this->morphTo();
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }
}
