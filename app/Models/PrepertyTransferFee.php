<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepertyTransferFee extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_percentage' => 'boolean',
    ];

    public function society()
    {
        return $this->belongsTo(Society::class, 'society_id');
    }
}
