<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransaction extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount_paid' => 'float',
        'charge_subtotal' => 'float',
        'tax_amount' => 'float',
        'late_fee_amount' => 'float',
        'tax_rate_percent' => 'float',
        'tax_capped' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function ownershipPlan(): BelongsTo
    {
        return $this->belongsTo(PropertyReservedPaymentPlan::class, 'ownership_payment_plan_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function ledgerRows(): BelongsToMany
    {
        return $this->belongsToMany(PropertyPaymentLedger::class, 'payment_transaction_allocations')
            ->withPivot('amount_allocated', 'tax_amount')
            ->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentTransactionAllocation::class, 'payment_transaction_id');
    }

    /** One row per charge of this payment that carried a late fee (collected or waived). */
    public function lateFees(): HasMany
    {
        return $this->hasMany(PaymentLateFee::class, 'payment_transaction_id');
    }

    /**
     * What goes back to the customer when this payment is returned: charges and
     * FBR tax, never the late fee.
     */
    public function getRefundableAmountAttribute(): float
    {
        return round(max(0.0, (float) $this->amount_paid - (float) $this->late_fee_amount), 2);
    }
}
