<?php

namespace App\Models;

use App\Enums\PlanType;
use App\Support\FbrTaxRateResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PropertyReservedPaymentPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'plan_type' => PlanType::class,
        'with_installment' => 'boolean',
        'fbr_tax_rate_percent' => 'float',
        'fbr_tax_amount' => 'float',
        'is_filer_at_commit' => 'boolean',
        'booking_fee_in_per' => 'boolean',
        'development_fee_in_per' => 'boolean',
        'development_fee_surplus' => 'boolean',
        'down_payment_in_per' => 'boolean',

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

    /**
     * For a transfer plan, the reservation plan it supersedes. Null for a
     * regular reservation plan.
     */
    public function parentPlan(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_plan_id');
    }

    public function childPlans(): HasMany
    {
        return $this->hasMany(self::class, 'parent_plan_id');
    }

    public function isTransfer(): bool
    {
        return $this->plan_type === PlanType::Transfer;
    }

    public function ledgerRows(): HasMany
    {
        return $this->hasMany(PropertyPaymentLedger::class, 'ownership_payment_plan_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'ownership_payment_plan_id');
    }

    /**
     * Whether any payment is still standing against this plan. A fully un-paid
     * transaction stays behind as a "returned" audit record, so it must not
     * keep the plan locked for edits.
     */
    public function hasLivePayments(): bool
    {
        return $this->paymentTransactions()
            ->where('status', '!=', 'returned')
            ->exists();
    }

    /**
     * The one cancellation record written when this reservation was closed
     * and the paid amount handed back.
     */
    public function cancellationReversal(): HasOne
    {
        return $this->hasOne(OwnershipCancellationReversal::class, 'plan_id');
    }

    /**
     * The person this plan was reserved for - the buyer, and therefore the
     * party whose filer status selects the FBR buyer rate.
     *
     * Named buyerUser() rather than buyer() because Laravel reserves singular
     * method names for relationships, and this resolves through two hops.
     */
    public function buyerUser(): ?\App\Models\User
    {
        return $this->propertyOwner?->owner;
    }

    /**
     * The FBR rate snapshotted on this plan, resolving it on first use.
     *
     * The slab is read once and then frozen so a later rate edit cannot
     * reprice payments that were already recorded. When the owning society
     * has FBR tax turned off in its system settings the plan is frozen at a
     * 0% rate instead, so no tax is ever collected for it.
     */
    public function fbrTaxRate(): float
    {
        if ($this->fbr_tax_rate_percent !== null) {
            return (float) $this->fbr_tax_rate_percent;
        }

        // A transfer plan carries only the society transfer fee - it is not a
        // property sale, so no FBR buyer tax applies and the resolver is skipped.
        if ($this->isTransfer()) {
            $this->forceFill([
                'fbr_tax_rate_percent' => 0.0,
                'is_filer_at_commit' => (bool) ($this->buyerUser()?->is_filer ?? false),
            ])->save();

            return 0.0;
        }

        $isFiler = (bool) ($this->buyerUser()?->is_filer ?? false);

        $rate = SocietySetting::includeFbrTax($this->societyId())
            ? app(FbrTaxRateResolver::class)->ratePercent(
                (float) ($this->final_price ?? 0),
                $isFiler ? FbrTaxRateResolver::FILER : FbrTaxRateResolver::NON_FILER
            )
            : 0.0;

        $this->forceFill([
            'fbr_tax_rate_percent' => $rate,
            'is_filer_at_commit' => $isFiler,
        ])->save();

        return $rate;
    }

    /**
     * The society this plan belongs to, reached through the reserved property
     * or property unit (unit -> floor -> property -> block -> society).
     */
    public function societyId(): ?int
    {
        $ownerable = $this->propertyOwnerable;

        if ($ownerable instanceof PropertyUnit) {
            return $ownerable->floor?->property?->block?->society_id;
        }

        if ($ownerable instanceof Property) {
            return $ownerable->block?->society_id;
        }

        return null;
    }
}
