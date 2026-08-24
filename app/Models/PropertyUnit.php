<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyUnit extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amenities' => 'array',
    ];

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

    public function attachment()
    {
        return $this->morphOne(Attachment::class, 'attachable')->where('is_main', true);
    }

    // for multiple documents related to society
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->where('is_main', false);
    }

    public function dimensions()
    {
        return $this->morphMany(Dimension::class, 'dimensionable');
    }

    public function listing()
    {
        return $this->morphOne(PropertyListing::class, 'listable');
    }

    public function favourites()
    {
        return $this->morphMany(VisitorFavourite::class, 'favouritable');
    }

    public function paymentPlan()
    {
        return $this->morphOne(PropertyPaymentPlan::class, 'planable');
    }
}
