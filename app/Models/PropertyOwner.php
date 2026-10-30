<?php

namespace App\Models;

use App\Enums\OwnershipStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PropertyOwner extends Model
{
    protected $table = 'property_ownership_history';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
        'status' => OwnershipStatus::class,
    ];

    protected static function booted(): void
    {
        // The active marker is the single guard that keeps one active ownership
        // per property/unit (its unique index). It is derived here so no caller
        // ever has to remember to set or clear it.
        static::saving(function (self $ownership) {
            $isActive = $ownership->ended_at === null
              && ($ownership->status === OwnershipStatus::Active || $ownership->status === null);

            $ownership->active_marker = $isActive
              ? $ownership->property_ownerable_type.':'.$ownership->property_ownerable_id
              : null;
        });
    }

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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The committed payment plan snapshot for this ownership.
     */
    public function paymentPlan()
    {
        return $this->hasOne(PropertyReservedPaymentPlan::class, 'property_owner_id');
    }

    /**
     * The transfer that created this ownership row (if any).
     */
    public function incomingTransfer(): BelongsTo
    {
        return $this->belongsTo(PropertyTransfer::class, 'transfer_id');
    }

    /**
     * The transfer that ended this ownership row (if any).
     */
    public function outgoingTransfer(): HasOne
    {
        return $this->hasOne(PropertyTransfer::class, 'from_ownership_id')->latest('id');
    }

    /**
     * The one active ownership of a property/unit.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', OwnershipStatus::Active);
    }

    /**
     * Ownerships that have ended (transferred or cancelled).
     */
    public function scopeEnded(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    /**
     * Whether this ownership record is currently active.
     */
    public function isCurrent(): bool
    {
        return $this->status === OwnershipStatus::Active;
    }

    public static function startDateForNow(): Carbon
    {
        return now()->startOfDay();
    }
}
