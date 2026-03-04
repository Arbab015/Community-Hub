<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable =
    [
        'name',
        'type',
        'is_main',
        'extension',
        'size',
        'link'
    ];


    public function attachable()
    {
        return $this->morphTo();
    }
}