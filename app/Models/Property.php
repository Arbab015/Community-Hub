<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_block_payment_plan_applied' => 'boolean',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function dimensions()
    {
        return $this->morphMany(Dimension::class, 'dimensionable');
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

    public function block()
    {
        return $this->belongsTo(Block::class, 'block_id');
    }

    public function floors()
    {
        return $this->hasMany(Floor::class, 'property_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function ownerships()
    {
        return $this->morphMany(PropertyOwner::class, 'property_ownerable');
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

    public function paymentPlans()
    {
        return $this->morphMany(PropertyPaymentPlan::class, 'planable');
    }

    public function reservedPaymentPlans()
    {
        return $this->morphMany(PropertyReservedPaymentPlan::class, 'property_ownerable');
    }
}
