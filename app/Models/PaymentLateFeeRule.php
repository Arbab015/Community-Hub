<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLateFeeRule extends Model
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PERCENTAGE = 'percentage';

    protected $guarded = [];

    protected $casts = [
        'grace_period_days' => 'integer',
        'fine_amount' => 'float',
    ];

    public function society(): BelongsTo
    {
        return $this->belongsTo(Society::class);
    }

    /** The "beyond all other tiers" rule has no grace period. */
    public function isOpenEnded(): bool
    {
        return $this->grace_period_days === null;
    }

    public function isPercentage(): bool
    {
        return $this->fine_type === self::TYPE_PERCENTAGE;
    }
}
