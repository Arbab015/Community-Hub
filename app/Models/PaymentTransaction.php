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
        return $this->belongsTo(PropertyOwnershipPaymentPlan::class, 'ownership_payment_plan_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function ledgerRows(): BelongsToMany
    {
        return $this->belongsToMany(PropertyPaymentLedger::class, 'payment_transaction_allocations')
            ->withPivot('amount_allocated')
            ->withTimestamps();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentTransactionAllocation::class, 'payment_transaction_id');
    }
}