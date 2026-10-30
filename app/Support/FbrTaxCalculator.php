<?php

namespace App\Support;

/**
 * FBR tax arithmetic for a payment plan.
 *
 * Tax is LINEAR in the charged amount, so it is independent of how a plan is
 * settled - one lump sum, one charge, or twenty charges over the same total
 * always produce the same tax:
 *
 *     tax = amount x rate / 100        and     sum(a_i x r) = (sum a_i) x r
 *
 * The only leak in that identity is per-transaction rounding, which can drift
 * a paisa over many transactions. A cumulative headroom cap closes it: tax can
 * never exceed round(final_price x rate / 100) and can never go negative, so
 * the final collected tax is EXACT regardless of how many transactions were used.
 */
final class FbrTaxCalculator
{
    /**
     * Total tax due on the whole plan at the given rate.
     */
    public function planTaxTotal(float $finalPrice, float $ratePercent): float
    {
        return round($finalPrice * $ratePercent / 100, 2);
    }

    /**
     * Tax carried by one transaction, limited by the plan-wide headroom.
     *
     * When $settlesPlan is true this payment clears the last outstanding charge,
     * so it absorbs the entire remaining headroom. That makes the sum of tax over
     * all transactions land EXACTLY on the target once a plan is fully settled,
     * rather than drifting a paisa low because every transaction rounded down.
     *
     * @return array{tax: float, target: float, headroom_before: float, proportional: float, capped: bool}
     */
    public function taxForTransaction(
        float $chargeAmount,
        float $ratePercent,
        float $taxAlreadyCollected,
        float $finalPrice,
        bool $settlesPlan = false
    ): array {
        $target = $this->planTaxTotal($finalPrice, $ratePercent);
        $headroom = round($target - $taxAlreadyCollected, 2);
        $proportional = round($chargeAmount * $ratePercent / 100, 2);

        if ($settlesPlan) {
            $tax = $headroom;
        } else {
            $tax = min($proportional, $headroom);
        }

        $tax = max($tax, 0.0);

        return [
            'tax' => round($tax, 2),
            'target' => $target,
            'headroom_before' => $headroom,
            'proportional' => $proportional,
            'capped' => $tax < $proportional,
        ];
    }

    /**
     * Tax already collected against a plan.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\PaymentTransaction>|iterable  $transactions
     */
    public function collectedSoFar(iterable $transactions): float
    {
        $total = 0.0;

        foreach ($transactions as $txn) {
            $total += (float) ($txn->tax_amount ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Split a transaction's tax across its allocations for display.
     *
     * The largest allocation absorbs the rounding residual so the parts always
     * sum back to $tax exactly - a printed receipt must never be a paisa out.
     *
     * @param  array<int, float>  $amounts
     * @return array<int, float>
     */
    public function prorate(float $tax, array $amounts): array
    {
        $amounts = array_values(array_map('floatval', $amounts));
        $count = count($amounts);

        if ($count === 0) {
            return [];
        }
        if ($count === 1) {
            return [round($tax, 2)];
        }

        $total = array_sum($amounts);
        if ($total <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $tax = round($tax, 2);
        $shares = [];
        $running = 0.0;

        foreach ($amounts as $i => $amount) {
            $share = $i === $count - 1 ? 0.0 : round($tax * ($amount / $total), 2);
            $shares[$i] = $share;
            $running += $share;
        }

        $residual = round($tax - $running, 2);
        if ($residual !== 0.0) {
            $largest = array_keys($amounts, max($amounts), true)[0];
            $shares[$largest] = round($shares[$largest] + $residual, 2);
        }

        return $shares;
    }
}
