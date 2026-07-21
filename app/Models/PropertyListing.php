<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PropertyListing extends Model
{
    use HasUuids;

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function listable()
    {
        return $this->morphTo();
    }

    public function inquiries()
    {
        return $this->hasMany(PropertyInquiry::class, 'listing_id');
    }
}
