<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockPriceRange extends Model
{
    protected $guarded = [];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }
}
