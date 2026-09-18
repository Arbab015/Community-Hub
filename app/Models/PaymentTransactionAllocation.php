<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransactionAllocation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_allocated' => 'float',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function ledgerRow(): BelongsTo
    {
        return $this->belongsTo(PropertyPaymentLedger::class, 'property_payment_ledger_id');
    }
}