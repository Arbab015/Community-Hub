<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Late fee carried by ONE payment against ONE charge.
 *
 * Non-refundable and never deleted: un-pay, return and cancellation leave these
 * rows untouched. A charge paid again after an un-pay gets a new row.
 */
class PaymentLateFee extends Model
{
    use HasUuids;

    public const STATUS_COLLECTED = 'collected';

    protected $guarded = [];

    protected $casts = [
        'fine_value' => 'float',
        'base_amount' => 'float',
        'fee_amount' => 'float',
        'days_late' => 'integer',
        'rule_grace_days' => 'integer',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function ledgerRow(): BelongsTo
    {
        return $this->belongsTo(PropertyPaymentLedger::class, 'property_payment_ledger_id')->withTrashed();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id')->withTrashed();
    }

    public function isPercentage(): bool
    {
        return $this->fine_type === PaymentLateFeeRule::TYPE_PERCENTAGE;
    }
}
