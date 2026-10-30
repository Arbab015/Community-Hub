<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbrTaxRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value_from' => 'integer',
            'value_to' => 'integer',
            'rate_percent' => 'float',
        ];
    }
}
