<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One record per cancelled ownership reservation: which committed plan was
 * closed, whose money was returned, who cancelled it, when and why.
 *
 * All context (property/unit, member, ownership) is reached through the plan,
 * so nothing is duplicated here - and the record is never a substitute for the
 * payment trail itself, which lives on as status changes only.
 */
class OwnershipCancellationReversal extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'ownership_cancellation_reversals';

    protected $guarded = [];

    protected $casts = [
        'return_amount' => 'float',
        'return_tax' => 'float',
        'return_total' => 'float',
        'commission_value' => 'float',
        'commission_base' => 'float',
        'commission_amount' => 'float',
        'payment_count' => 'integer',
        'reversed_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PropertyReservedPaymentPlan::class, 'plan_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
