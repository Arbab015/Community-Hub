<?php

namespace App\Support;

/**
 * Single source of truth for installment-plan math, shared by the PHP blade
 * views and mirrored (without duplication) by the plain-JS
 * resources/assets/js/installment-plan-calculator.js.
 *
 * Frequency hierarchy: installments run monthly < quarterly < biannually <
 * annually. A balloon payment must be *less frequent* than the installments
 * (higher rank), so a balloon lands at the end of each frequency block.
 *
 * Key formulas:
 *   - installments per year: monthly=12, quarterly=4, biannually=2, annually=1
 *   - balloons per year:     quarterly=4, biannually=2, annually=1
 *   - total years  = number_of_installments / installments_per_year
 *   - balloon count = (total years x balloons_per_year) - 1  (final event is a normal installment)
 *   - balloon step = installments_per_year / balloons_per_year
 *   - total balloon = grand total x percentage (or a fixed amount)
 *   - per balloon    = total balloon / balloon count
 *   - per installment = (grand total - total balloon) / normal count
 *
 * Calculation flow:
 *   - gross amount       = listing price (= listed price + feature premiums)
 *   - included charges   = one-time charges carved out of gross
 *   - excluded charges   = extra one-time charges billed on top (not subtracted)
 *   - remaining amount   = gross amount - included charges
 *   - extra installment  = additional installment amount (% of gross or fixed)
 *   - grand total        = remaining amount + extra installment
 *   - total balloon      = grand total x percentage (or a fixed amount)
 *   - per balloon        = total balloon / balloon count
 *   - per installment    = (grand total - total balloon) / normal count
 *   - total payable      = included + excluded + grand total
 *                       = gross amount + excluded charges + extra installment
 *
 * Per-installment and per-balloon amounts are always rounded UP (ceil) so no
 * payment ever carries awkward decimals like .08.
 */
final class InstallmentPlanCalculator
{
    public const INSTALLMENTS_PER_YEAR = [
        'monthly' => 12,
        'quarterly' => 4,
        'biannually' => 2,
        'annually' => 1,
    ];

    public const BALLOONS_PER_YEAR = [
        'quarterly' => 4,
        'biannually' => 2,
        'annually' => 1,
    ];

    public const FREQUENCY_RANK = [
        'monthly' => 1,
        'quarterly' => 2,
        'biannually' => 3,
        'annually' => 4,
    ];

    public static function installmentsPerYear(string $frequency): int
    {
        return self::INSTALLMENTS_PER_YEAR[$frequency] ?? 12;
    }

    public static function balloonsPerYear(string $frequency): int
    {
        return self::BALLOONS_PER_YEAR[$frequency] ?? 4;
    }

    /**
     * A balloon frequency is valid only when it is less frequent than the
     * installment frequency (strictly higher rank), e.g. quarterly balloons
     * with monthly installments.
     */
    public static function balloonFrequencyValid(?string $installmentFrequency, ?string $balloonFrequency): bool
    {
        if (! $installmentFrequency || ! $balloonFrequency) {
            return false;
        }

        $installmentRank = self::FREQUENCY_RANK[$installmentFrequency] ?? 0;
        $balloonRank = self::FREQUENCY_RANK[$balloonFrequency] ?? 0;

        return $balloonRank > $installmentRank;
    }

    /**
     * Number of balloon payments for a whole-year plan. The last event of the
     * plan is always a normal installment, hence the "- 1".
     */
    public static function balloonCount(int $numberOfInstallments, string $installmentFrequency, string $balloonFrequency): int
    {
        $years = $numberOfInstallments / self::installmentsPerYear($installmentFrequency);

        return max(0, (int) ceil($years * self::balloonsPerYear($balloonFrequency)) - 1);
    }

    /**
     * Computes the full breakdown and an interleaved payment schedule.
     *
     * Calculation flow:
     *   gross       = listing (gross) price
     *   remaining   = gross - included charges (excluded charges are extra,
     *                 billed separately on top, never subtracted)
     *   grand total = remaining + extra installment amount
     *   balloon     = % of grand total (or fixed)
     *   per balloon / per installment derived from grand total
     *
     * Total payable (final amount) = included + excluded + grand total
     *                              = gross + excluded + extra installment.
     *
     * Expected input keys:
     *   listing_price, included_charges_total, excluded_charges_total,
     *   additional_amount, number_of_installments, installment_frequency,
     *   balloon_payment (raw), balloon_payment_in_per (bool),
     *   balloon_payment_frequency
     */
    public static function calculate(array $input): array
    {
        $listingPrice = (float) ($input['listing_price'] ?? 0);
        $includedTotal = (float) ($input['included_charges_total'] ?? 0);
        $excludedTotal = (float) ($input['excluded_charges_total'] ?? 0);
        $additional = (float) ($input['additional_amount'] ?? 0);
        $num = (int) ($input['number_of_installments'] ?? 0);
        $installmentFrequency = $input['installment_frequency'] ?? 'monthly';
        $balloonFrequency = $input['balloon_payment_frequency'] ?? null;

        $balloonRaw = 0.0;
        if (isset($input['balloon_payment'])
            && $input['balloon_payment'] !== null
            && $input['balloon_payment'] !== '') {
            $balloonRaw = (float) $input['balloon_payment'];
        }
        $balloonPer = (bool) ($input['balloon_payment_in_per'] ?? false);

        $ipp = self::installmentsPerYear($installmentFrequency);
        $bpy = $balloonFrequency ? self::balloonsPerYear($balloonFrequency) : 0;
        $years = $num > 0 ? $num / $ipp : 0;

        // ── Core amounts ──
        // Gross = the listing (gross) price passed in
        $grossAmount = $listingPrice;
        // One-time charges carved out of gross = included charges only. Excluded
        // charges are extra one-time charges billed on top, not subtracted.
        $oneTimeCharges = $includedTotal;
        // Remaining = gross minus included charges
        $remainingAmount = $grossAmount - $includedTotal;
        // Extra = installment-specific markup (% of gross or fixed)
        $extraInstallments = $additional;
        // Grand total = remaining + extra (the amount actually paid via installments)
        $grandTotal = $remainingAmount + $extraInstallments;

        $errors = [];
        $hasBalloon = $balloonRaw > 0;

        if ($hasBalloon) {
            if (! $balloonFrequency) {
                $errors[] = 'Balloon frequency is required when balloon payment is set';
            } elseif (! self::balloonFrequencyValid($installmentFrequency, $balloonFrequency)) {
                $errors[] = 'Balloon frequency must be less frequent than the installment frequency';
            }
        }

        // Balloon is calculated from the grand total
        $totalBalloon = $balloonPer ? $grandTotal * $balloonRaw / 100 : $balloonRaw;

        if ($hasBalloon && empty($errors)) {
            $balloonCount = self::balloonCount($num, $installmentFrequency, $balloonFrequency);
            $normalCount = max(0, $num - $balloonCount);
            $step = $bpy > 0 ? intdiv($ipp, $bpy) : 0;
            $perBalloon = $balloonCount > 0 ? (int) ceil($totalBalloon / $balloonCount) : 0;
            $perInstallment = $normalCount > 0 ? (int) ceil(($grandTotal - $totalBalloon) / $normalCount) : 0;
        } else {
            $balloonCount = 0;
            $normalCount = $num;
            $step = 0;
            $perBalloon = 0;
            $perInstallment = $num > 0 ? (int) ceil($grandTotal / $num) : 0;
        }

        $schedule = [];
        $cumulative = 0;
        $balloonIndex = 0;
        $installmentNo = 0;

        for ($i = 1; $i <= $num; $i++) {
            $isBalloon = $balloonCount > 0 && $step > 0 && $i % $step === 0 && $balloonIndex < $balloonCount;

            if ($isBalloon) {
                $balloonIndex++;
                $amount = $perBalloon;
                $cumulative += $amount;
                $schedule[] = [
                    'no' => $i,
                    'type' => 'balloon',
                    'balloon_no' => $balloonIndex,
                    'amount' => $amount,
                    'cumulative' => $cumulative,
                ];
            } else {
                $installmentNo++;
                $amount = $perInstallment;
                $cumulative += $amount;
                $schedule[] = [
                    'no' => $installmentNo,
                    'type' => 'installment',
                    'balloon_no' => null,
                    'amount' => $amount,
                    'cumulative' => $cumulative,
                ];
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'installments_per_year' => $ipp,
            'balloons_per_year' => $bpy,
            'total_years' => $years,
            'step' => $step,
            'gross_amount' => $grossAmount,
            'one_time_charges' => $oneTimeCharges,
            'remaining_amount' => $remainingAmount,
            'additional_charges' => $excludedTotal,
            'extra_installments' => $extraInstallments,
            'grand_total' => $grandTotal,
            'total_balloon' => $totalBalloon,
            'installment_remaining' => $grandTotal - $totalBalloon,
            'balloon_count' => $balloonCount,
            'normal_count' => $normalCount,
            'per_balloon' => $perBalloon,
            'per_installment' => $perInstallment,
            'plan_total' => $grandTotal,
            'schedule_total' => $cumulative,
            'schedule' => $schedule,
        ];
    }
}
