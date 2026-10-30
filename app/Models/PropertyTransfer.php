<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A member's request to hand a property/unit ownership to another member,
 * awaiting the society owner's approval. The society transfer fee is frozen
 * on the request; approval ends the outgoing ownership, starts the incoming
 * one and creates the transfer-fee payment plan.
 */
class PropertyTransfer extends Model
{
    use HasUuids;

    protected $table = 'property_transfers';

    protected $guarded = [];

    protected $casts = [
        'transfer_fee' => 'float',
        'transfer_fee_in_per' => 'boolean',
        'fee_base' => 'float',
        'fee_amount' => 'float',
        'acted_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function propertyOwnerable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The ownership row this transfer ended.
     */
    public function fromOwnership(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'from_ownership_id');
    }

    /**
     * The fully-paid plan that belonged to the outgoing ownership.
     */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(PropertyReservedPaymentPlan::class, 'from_plan_id');
    }

    /**
     * The transfer-fee plan created for the incoming ownership on approval.
     */
    public function newPlan(): BelongsTo
    {
        return $this->belongsTo(PropertyReservedPaymentPlan::class, 'new_plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class, 'society_id');
    }

    public function transferFee(): BelongsTo
    {
        return $this->belongsTo(PrepertyTransferFee::class, 'transfer_fee_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * The frozen absolute fee. `transfer_fee` holds the rate for percentage
     * fees, while `fee_amount` always holds the computed amount.
     */
    public function feeAmount(): float
    {
        if ($this->fee_amount !== null) {
            return (float) $this->fee_amount;
        }

        return $this->transfer_fee_in_per
            ? round((float) $this->fee_base * (float) $this->transfer_fee / 100, 2)
            : (float) $this->transfer_fee;
    }
}
