<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlockPaymentPlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'installment_template_ids' => 'array',
        'is_commercial' => 'boolean',
        'apply_to_units' => 'boolean',
    ];

    public function block()
    {
        return $this->belongsTo(Block::class);
    }

    public function paymentTemplate()
    {
        return $this->belongsTo(PaymentTemplate::class, 'payment_template_id');
    }
}
