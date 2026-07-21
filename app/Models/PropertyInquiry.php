<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PropertyInquiry extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function listing()
    {
        return $this->belongsTo(Property::class);
    }

    public function visitor()
    {
        return $this->belongsTo(Visitor::class);
    }
}
