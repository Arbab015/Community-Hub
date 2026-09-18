<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PropertyPaymentLedger extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'sequence_order' => 'integer',
        'installment_no' => 'integer',
        'balloon_no' => 'integer',
        'amount_due' => 'float',
        'amount_paid' => 'float',
        'remaining_balance' => 'float',
        'due_date' => 'date',
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

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(PaymentTransaction::class, 'payment_transaction_allocations')
            ->withPivot('amount_allocated')
            ->withTimestamps();
    }

    public function isPaid(): bool
    {
        return (int) round($this->remaining_balance * 100) <= 0;
    }
}