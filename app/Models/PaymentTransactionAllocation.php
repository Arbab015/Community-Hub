<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTransactionAllocation extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'amount_allocated' => 'float',
        'tax_amount' => 'float',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function ledgerRow(): BelongsTo
    {
        return $this->belongsTo(PropertyPaymentLedger::class, 'property_payment_ledger_id')->withTrashed();
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }
}
