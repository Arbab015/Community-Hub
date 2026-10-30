<?php

namespace App\Support;

use App\Models\PaymentLateFeeRule;
use App\Models\Property;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyUnit;
use App\Models\Society;
use App\Models\SocietySetting;
use Carbon\Carbon;

/**
 * Single source of truth for late fee calculations.
 *
 * A fee is calculated separately for every charge allocation:
 *
 *   days_late = paid_at (date) - due_date (date)
 *
 * The smallest grace period among the society's rules is the free period: a
 * charge is not fined while days_late <= that minimum. After it, the rule with
 * the highest grace period that has already been crossed applies. For example,
 * with rules at 15, 30 and 43 days: days 16-30 use the 15-day rule, days 31-43
 * use the 30-day rule, and days 44+ use the 43-day rule. A rule with no grace
 * period (null) is the catch-all after the last tier, or applies from day 1
 * when it is the only rule.
 *
 * Percentage rules charge a share of the allocated amount (partial payments
 * included). Fixed rules charge once, only on the payment that clears the
 * charge.
 */
final class LateFeeService
{
    public const STATUS_COLLECTED = 'collected';

    /** @var array<int, \Illuminate\Support\Collection<int, PaymentLateFeeRule>> */
    private array $rulesCache = [];

    /** @var array<int, bool> */
    private array $enabledCache = [];

    /**
     * The society a committed plan belongs to:
     * Property -> block -> society, or Unit -> floor -> property -> block -> society.
     */
    public function societyOf(PropertyReservedPaymentPlan $plan): ?Society
    {
        $ownerable = $plan->propertyOwnerable;

        if ($ownerable instanceof PropertyUnit) {
            return $ownerable->floor?->property?->block?->society;
        }

        if ($ownerable instanceof Property) {
            return $ownerable->block?->society;
        }

        return null;
    }

    /**
     * Rules for a society, sorted for tier selection: every rule with a grace
     * period first (ascending), then the null-grace catch-all rule.
     *
     * @return \Illuminate\Support\Collection<int, PaymentLateFeeRule>
     */
    public function rulesFor(?Society $society)
    {
        if (! $society) {
            return collect();
        }

        if (! array_key_exists($society->id, $this->rulesCache)) {
            $this->rulesCache[$society->id] = PaymentLateFeeRule::query()
                ->where('society_id', $society->id)
                ->get()
                ->sortBy(fn (PaymentLateFeeRule $rule) => $rule->grace_period_days === null
                    ? PHP_INT_MAX
                    : (int) $rule->grace_period_days)
                ->values();
        }

        return $this->rulesCache[$society->id];
    }

    /**
     * The smallest day threshold among the society's rules. This is the free
     * (concession) period: no fee is charged while days_late <= this value.
     * Null means the society has no rules at all; 0 means it only has a
     * catch-all rule (so the fee starts from the first late day).
     */
    public function minimumGraceDays(?Society $society): ?int
    {
        $rules = $this->rulesFor($society);

        if ($rules->isEmpty()) {
            return null;
        }

        $tiers = $rules->filter(fn (PaymentLateFeeRule $rule) => $rule->grace_period_days !== null);

        return $tiers->isEmpty() ? 0 : (int) $tiers->min('grace_period_days');
    }

    public function minimumGraceDaysForPlan(PropertyReservedPaymentPlan $plan): ?int
    {
        if ($plan->isTransfer()) {
            return null;
        }

        return $this->minimumGraceDays($this->societyOf($plan));
    }

    /**
     * A late fee applies only when the society enables it, has at least one
     * rule, and the plan is not a transfer-fee plan.
     */
    public function isApplicable(PropertyReservedPaymentPlan $plan): bool
    {
        if ($plan->isTransfer()) {
            return false;
        }

        $society = $this->societyOf($plan);

        if (! $society) {
            return false;
        }

        if (! array_key_exists($society->id, $this->enabledCache)) {
            $this->enabledCache[$society->id] = (bool) SocietySetting::get(
                $society->id,
                SocietySetting::ENABLE_LATE_FEE_RULE,
                false
            );
        }

        return $this->enabledCache[$society->id]
          && $this->rulesFor($society)->isNotEmpty();
    }

    /**
     * Calculate the late fee for each allocation draft.
     *
     * Each draft must contain:
     *   ledger        - the PropertyPaymentLedger row being paid
     *   amount        - the amount allocated to that row in this payment
     *   settles_row   - whether the allocation clears the row's balance
     *
     * @param  iterable<int, array{ledger: PropertyPaymentLedger, amount: float, settles_row?: bool}>  $allocationDrafts
     * @return array{charges: list<array<string, mixed>>, total: float}
     */
    public function calculate(PropertyReservedPaymentPlan $plan, iterable $allocationDrafts, Carbon|string|null $paidAt): array
    {
        if (! $this->isApplicable($plan)) {
            return ['charges' => [], 'total' => 0.0];
        }

        $society = $this->societyOf($plan);
        $rules = $this->rulesFor($society);

        if ($rules->isEmpty()) {
            return ['charges' => [], 'total' => 0.0];
        }

        $paidOn = ($paidAt !== null ? Carbon::parse($paidAt) : now())->copy()->startOfDay();

        // A society can only have one null-grace rule; if legacy data has more,
        // the first one (lowest id) is the catch-all.
        $nullRule = $rules->first(fn (PaymentLateFeeRule $rule) => $rule->grace_period_days === null);

        $tiers = $rules
            ->filter(fn (PaymentLateFeeRule $rule) => $rule->grace_period_days !== null)
            ->sortBy('grace_period_days')
            ->values();

        $minimumGraceDays = $tiers->isEmpty() ? 0 : (int) $tiers->first()->grace_period_days;
        $highestTierDays = $tiers->isEmpty() ? null : (int) $tiers->last()->grace_period_days;

        $charges = [];
        $total = 0.0;

        foreach ($allocationDrafts as $draft) {
            $row = $draft['ledger'] ?? null;

            if (! $row instanceof PropertyPaymentLedger || ! $row->due_date) {
                continue;
            }

            $due = Carbon::parse($row->due_date)->startOfDay();

            if (! $paidOn->greaterThan($due)) {
                continue;
            }

            $daysLate = (int) $due->diffInDays($paidOn);

            if ($daysLate <= 0) {
                continue;
            }

            // While inside the smallest grace period no fine is due at all.
            if ($daysLate <= $minimumGraceDays) {
                continue;
            }

            // A rule starts the day AFTER its grace period: 16-30 uses the
            // 15-day rule, 31-43 uses the 30-day rule, and so on.
            $rule = $tiers
                ->filter(fn (PaymentLateFeeRule $tier) => (int) $tier->grace_period_days < $daysLate)
                ->last();

            // Past the last numbered tier: use the catch-all rule when one is
            // configured, otherwise the last tier keeps running.
            if ($nullRule && ($rule === null || ($highestTierDays !== null && $daysLate > $highestTierDays))) {
                $rule = $nullRule;
            }

            if (! $rule) {
                continue;
            }

            $base = self::round2((float) ($draft['amount'] ?? 0));

            if ($base <= 0) {
                continue;
            }

            if ($rule->isPercentage()) {
                $fee = self::round2($base * (float) $rule->fine_amount / 100);
            } else {
                // A fixed fee is only charged when this payment clears the charge.
                if (! ($draft['settles_row'] ?? false)) {
                    continue;
                }

                $fee = self::round2((float) $rule->fine_amount);
            }

            if ($fee <= 0) {
                continue;
            }

            $charges[] = [
                'late_fee_rule_id' => $rule->id,
                'property_payment_ledger_id' => $row->id,
                'ledger_id' => $row->id,
                'display_name' => $row->display_name,
                'fine_type' => $rule->fine_type,
                'fine_value' => self::round2((float) $rule->fine_amount),
                'rule_grace_days' => $rule->grace_period_days,
                'days_late' => $daysLate,
                'base_amount' => $base,
                'fee_amount' => $fee,
                'status' => self::STATUS_COLLECTED,
            ];

            $total = self::round2($total + $fee);
        }

        return ['charges' => $charges, 'total' => $total];
    }

    public static function round2(float $value): float
    {
        return round($value, 2);
    }
}
