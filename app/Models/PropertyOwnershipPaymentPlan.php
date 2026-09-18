<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PropertyOwnershipPaymentPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'with_installment' => 'boolean',
        'booking_fee_in_per' => 'boolean',
        'development_fee_in_per' => 'boolean',
        'development_fee_surplus' => 'boolean',
        'down_payment_in_per' => 'boolean',
        'transfer_fee_in_per' => 'boolean',
        'transfer_fee_surplus' => 'boolean',
        'confirmation_fee_in_per' => 'boolean',
        'possession_fee_in_per' => 'boolean',
        'possession_fee_surplus' => 'boolean',
        'corner_premium_in_per' => 'boolean',
        'park_facing_premium_in_per' => 'boolean',
        'main_boulevard_premium_in_per' => 'boolean',
        'additional_installment_amount_in_per' => 'boolean',
        'balloon_payment_in_per' => 'boolean',
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

    public function propertyOwner(): BelongsTo
    {
        return $this->belongsTo(PropertyOwner::class, 'property_owner_id');
    }

    public function ledgerRows(): HasMany
    {
        return $this->hasMany(PropertyPaymentLedger::class, 'ownership_payment_plan_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'ownership_payment_plan_id');
    }
}
