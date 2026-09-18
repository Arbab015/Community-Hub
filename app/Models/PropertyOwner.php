<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PropertyOwner extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * The property or property unit this ownership belongs to.
     */
    public function propertyOwnerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The member user who owns the property/unit.
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The committed payment plan snapshot for this ownership.
     */
    public function paymentPlan()
    {
        return $this->hasOne(PropertyOwnershipPaymentPlan::class, 'property_owner_id');
    }

    /**
     * Whether this ownership record is currently active (not ended).
     */
    public function isCurrent(): bool
    {
        return $this->end_date === null;
    }

    public static function startDateForNow(): Carbon
    {
        return now()->startOfDay();
    }
}
