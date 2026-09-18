<?php

namespace App\Support;

use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionAllocation;
use App\Models\PropertyOwnershipPaymentPlan;
use App\Models\PropertyPaymentLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for building and maintaining the payment ledger of a
 * committed ownership snapshot (property_ownership_payment_plans).
 *
 * The ledger is derived ONLY from PaymentPlanPresenter::buildFromSnapshot() -
 * never recomputed from raw snapshot columns - so the ledger always agrees with
 * the Charges card and the printed contract. Invariant:
 *
 *   SUM(property_payment_ledger.amount_due) === snapshot.final_price
 */
final class PaymentLedgerService
{
    /**
     * Maps a presenter fee label back to the canonical charge_type key.
     */
    private const CHARGE_TYPE_LABELS = [
        'Booking Fee' => 'booking_fee',
        'Development Fee' => 'development_fee',
        'Down Payment' => 'down_payment',
        'Transfer Fee' => 'transfer_fee',
        'Confirmation Fee' => 'confirmation_fee',
        'Possession Fee' => 'possession_fee',
    ];

    public function createLedgerFromSnapshot(
        PropertyOwnershipPaymentPlan $snapshot,
        bool $force = false,
        ?Carbon $anchor = null
    ): void {
        $exists = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->exists();

        if ($exists && ! $force) {
            return;
        }
        if ($force && $snapshot->paymentTransactions()->exists()) {
            throw new \LogicException('Ledger cannot be rebuilt once payments are recorded.');
        }

        if ($force) {
            PropertyPaymentLedger::query()
                ->where('ownership_payment_plan_id', $snapshot->id)
                ->forceDelete();
        }

        $data = PaymentPlanPresenter::buildFromSnapshot($snapshot);
        $finalPrice = self::round2((float) ($data['final_price'] ?? 0));

        // Excluded (surplus) fees — development, transfer, possession — are the
        // amounts the InstallmentPlanCalculator adds on top of the gross and
        // bills *inside* the installment schedule (grand_total already includes
        // them). Emitting them again as separate ledger rows would double-count,
        // forcing balanceTo() to make the last row (possession fee) negative.
        $hasSchedule = (bool) $snapshot->with_installment && (int) ($snapshot->number_of_installments ?? 0) > 0;
        $isExcludedFee = fn (array $fee) => in_array($fee['label'] ?? '', ['Development Fee', 'Transfer Fee', 'Possession Fee']);

        $rows = [];
        $sequence = 0;

        $anchor ??= $snapshot->propertyOwner?->start_date ? Carbon::parse($snapshot->propertyOwner->start_date) : now()->startOfDay();

        // 1) Up-front charges (booking + confirmation fee) due immediately.
        foreach ($this->feeRowsByLabel($data['fee_rows'] ?? [], ['Booking Fee', 'Confirmation Fee']) as $fee) {
            $rows[] = $this->rowForCharge(
                self::CHARGE_TYPE_LABELS[$fee['label']],
                $fee['amount'],
                $anchor,
                ++$sequence,
                $anchor
            );
        }

        // 2) Core charges (development / down payment / transfer fee) on their own
        //    due dates. With an installment schedule the excluded ones are inside
        //    the schedule, so only the down payment is billed as a separate row.
        foreach ($this->feeRowsByLabel($data['fee_rows'] ?? [], ['Development Fee', 'Down Payment', 'Transfer Fee']) as $fee) {
            if ($hasSchedule && $isExcludedFee($fee)) {
                continue;
            }
            $rows[] = $this->rowForCharge(
                self::CHARGE_TYPE_LABELS[$fee['label']],
                $fee['amount'],
                $anchor,
                ++$sequence
            );
        }

        // 3) Installment schedule (or the balance lump when none exists).
        $rows = array_merge($rows, $this->buildRemainderRows($snapshot, $data, $finalPrice, $sequence, $anchor));

        // 4) Possession fee last, due one period after the final installment.
        //    With an installment schedule the possession amount is already part of
        //    the schedule, so it is only billed as a separate row otherwise.
        $possession = $this->feeRowsByLabel($data['fee_rows'] ?? [], ['Possession Fee']);
        if ($possession && ! $hasSchedule) {
            $latestDue = null;
            foreach ($rows as $r) {
                if ($r['due_date'] instanceof Carbon) {
                    $latestDue = ($latestDue === null || $r['due_date']->gt($latestDue)) ? $r['due_date'] : $latestDue;
                }
            }
            $months = self::monthsPerPeriod($snapshot->installment_frequency ?: 'monthly');
            $dueDate = $latestDue
                ? $latestDue->copy()->addMonths($months)
                : $anchor->copy()->addMonths(3);
            $rows[] = $this->rowForCharge('possession_fee', $possession[0]['amount'], $anchor, count($rows) + 1, $dueDate);
        }

        // 5) Attach the balancing difference so SUM(amount_due) === final_price.
        $this->balanceTo($rows, $finalPrice);

        foreach ($rows as $row) {
            $row['ownership_payment_plan_id'] = $snapshot->id;
            PropertyPaymentLedger::create($row);
        }
    }

    /**
     * Record a payment against a snapshot. Cascades through the cheapest
     * outstanding rows in sequence order unless a specific row is targeted.
     *
     * @throws \InvalidArgumentException when paying more than the outstanding balance
     */
    public function applyPayment(PropertyOwnershipPaymentPlan $snapshot, array $data, ?int $targetLedgerId = null): PaymentTransaction
    {
        $amount = self::round2((float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $paidAt = isset($data['paid_at']) && $data['paid_at'] !== '' && $data['paid_at'] !== null
            ? Carbon::parse($data['paid_at'])
            : now();

        $rowsQuery = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id);

        if ($targetLedgerId !== null) {
            $rows = $rowsQuery->whereKey($targetLedgerId)->get();
        } else {
            $rows = $rowsQuery
                ->orderBy('sequence_order')
                ->get()
                ->filter(fn ($r) => ! $r->isPaid())
                ->values();
        }

        if ($rows->isEmpty()) {
            throw new \InvalidArgumentException('Nothing outstanding to pay.');
        }

        $outstanding = $rows->sum(fn ($r) => (float) $r->remaining_balance);
        if ($amount > self::round2($outstanding)) {
            throw new \InvalidArgumentException('Payment exceeds the outstanding balance.');
        }

        return DB::transaction(function () use ($snapshot, $rows, $amount, $paidAt, $data) {
            $remainingToAllocate = $amount;
            $allocations = [];

            foreach ($rows as $row) {
                if ($remainingToAllocate <= 0.004) {
                    break;
                }
                $rowRemaining = (float) $row->remaining_balance;
                if ($rowRemaining <= 0.004) {
                    continue;
                }

                $alloc = min($remainingToAllocate, self::round2($rowRemaining));
                $alloc = self::round2($alloc);
                if ($alloc <= 0) {
                    continue;
                }

                $allocations[] = [
                    'ledger' => $row,
                    'amount' => $alloc,
                ];

                $newPaid = self::round2((float) $row->amount_paid + $alloc);
                $newRemaining = self::round2((float) $row->remaining_balance - $alloc);

                $row->forceFill([
                    'amount_paid' => $newPaid,
                    'remaining_balance' => $newRemaining,
                ]);

                if (self::round2($newRemaining) <= 0) {
                    $row->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => $paidAt,
                    ]);
                }

                $row->save();
                $remainingToAllocate = self::round2($remainingToAllocate - $alloc);
            }

            $transaction = PaymentTransaction::create([
                'ownership_payment_plan_id' => $snapshot->id,
                'amount_paid' => $amount,
                'payment_method' => $data['payment_method'] ?? 'other',
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? null,
                'recorded_by_user_id' => Auth::id(),
                'paid_at' => $paidAt,
            ]);

            foreach ($allocations as $alloc) {
                PaymentTransactionAllocation::create([
                    'payment_transaction_id' => $transaction->id,
                    'property_payment_ledger_id' => $alloc['ledger']->id,
                    'amount_allocated' => $alloc['amount'],
                ]);
            }

            if ($this->summary($snapshot)['remaining'] <= 0) {
                $snapshot->update(['status' => 'completed']);
            }

            return $transaction;
        });
    }

    /**
     * The date-based status rules (only PAID is balance-based).
     *
     * Unpaid charges that have fallen past their due date become "late" (or
     * "current_due" within the 7-day grace window). Any unpaid charge whose due
     * date is the next outstanding date (or today) is labelled "current_due" so
     * the row the owner should pay now is always shown as current due.
     */
    public function determineStatus(PropertyPaymentLedger $row, ?Carbon $today = null, ?Carbon $nextDue = null): string
    {
        if ($row->isPaid()) {
            return 'paid';
        }

        $today = ($today ?? now())->copy()->startOfDay();
        $due = $row->due_date ? $row->due_date->copy()->startOfDay() : null;

        if (! $due) {
            return 'pending';
        }

        if ($due->lessThan($today)) {
            $daysOverdue = (int) $today->diffInDays($due);

            return $daysOverdue <= 7 ? 'current_due' : 'late';
        }

        if ($nextDue !== null && $due->equalTo($nextDue)) {
            return 'current_due';
        }

        return 'pending';
    }

    /**
     * Reverse a payment transaction: restore each affected ledger row's
     * balance and remove the allocations + transaction itself.
     */
    public function reversePayment(PaymentTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $snapshot = $transaction->ownershipPlan;

            foreach ($transaction->allocations()->with('ledgerRow')->get() as $alloc) {
                $row = $alloc->ledgerRow;
                if (! $row) {
                    continue;
                }

                $newPaid = self::round2((float) $row->amount_paid - (float) $alloc->amount_allocated);
                $row->forceFill([
                    'amount_paid' => max(0, $newPaid),
                    'remaining_balance' => self::round2((float) $row->remaining_balance + (float) $alloc->amount_allocated),
                    'payment_status' => 'pending',
                    'paid_at' => null,
                ]);
                $row->save();
            }

            $transaction->allocations()->delete();
            $transaction->forceDelete();

            if ($snapshot && $snapshot->status === 'completed') {
                $snapshot->update(['status' => 'active']);
            }
            $this->refreshStatuses($snapshot);
        });
    }

    /**
     * Re-run determineStatus() over one plan (or all plans with a ledger).
     */
    public function refreshStatuses(?PropertyOwnershipPaymentPlan $snapshot = null): void
    {
        $plans = $snapshot
            ? collect([$snapshot])
            : PropertyOwnershipPaymentPlan::query()->whereHas('ledgerRows')->get();

        foreach ($plans as $plan) {
            $rows = PropertyPaymentLedger::query()
                ->where('ownership_payment_plan_id', $plan->id)
                ->get();

            $nextDue = null;
            foreach ($rows as $row) {
                if ($row->isPaid() || ! $row->due_date) {
                    continue;
                }
                $due = $row->due_date->copy()->startOfDay();
                if ($nextDue === null || $due->lessThan($nextDue)) {
                    $nextDue = $due;
                }
            }

            foreach ($rows as $row) {
                $status = $this->determineStatus($row, null, $nextDue);
                if ($row->payment_status !== $status) {
                    $row->update(['payment_status' => $status]);
                }
            }
        }
    }

    public function summary(PropertyOwnershipPaymentPlan $snapshot): array
    {
        $rows = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->get();

        $nextDue = null;
        foreach ($rows as $row) {
            if ($row->isPaid() || ! $row->due_date) {
                continue;
            }
            $due = $row->due_date->copy()->startOfDay();
            if ($nextDue === null || $due->lessThan($nextDue)) {
                $nextDue = $due;
            }
        }

        $totalDue = 0.0;
        $totalPaid = 0.0;
        $byStatus = ['pending' => 0, 'current_due' => 0, 'late' => 0, 'paid' => 0];
        $overdueDays = 0;

        foreach ($rows as $row) {
            $totalDue += (float) $row->amount_due;
            $totalPaid += (float) $row->amount_paid;
            $status = $this->determineStatus($row, null, $nextDue);
            $byStatus[$status]++;
            $row->payment_status = $status;

            if (! $row->isPaid() && $row->due_date) {
                $due = $row->due_date->copy()->startOfDay();
                if ($due->lessThan(now()->startOfDay())) {
                    $overdueDays = max($overdueDays, (int) now()->startOfDay()->diffInDays($due));
                }
            }
        }

        $totalDue = self::round2($totalDue);
        $totalPaid = self::round2($totalPaid);

        return [
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'remaining' => self::round2($totalDue - $totalPaid),
            'by_status' => $byStatus,
            'next_due_date' => $nextDue,
            'overdue_days' => $overdueDays,
            'paid_percent' => $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 1) : 0,
        ];
    }

    /**
     * Delete + recreate the ledger. Only allowed while no payments exist.
     */
    public function rebuildFromSnapshot(PropertyOwnershipPaymentPlan $snapshot, ?Carbon $anchor = null): void
    {
        $this->createLedgerFromSnapshot($snapshot, force: true, anchor: $anchor);
    }

    /**
     * Other ledger row types (fees) emitted for a single charge.
     */
    private function rowForCharge(
        string $chargeType,
        float $amount,
        Carbon $anchor,
        int $sequence,
        ?Carbon $dueDateOverride = null
    ): array {
        $dueDate = $dueDateOverride ?? match ($chargeType) {
            'booking_fee' => $anchor->copy(),
            'confirmation_fee' => $anchor->copy(),
            'development_fee' => $anchor->copy(),
            'down_payment' => $anchor->copy(),
            'transfer_fee' => $anchor->copy(),
            'possession_fee' => $anchor->copy()->addMonths(3),
            default => null,
        };

        return $this->baseRow(
            $chargeType,
            self::chargeLabel($chargeType),
            $amount,
            $sequence,
            $dueDate
        );
    }

    /**
     * Return fee rows for the given labels, keeping the requested order.
     */
    private function feeRowsByLabel(array $feeRows, array $labels): array
    {
        $byLabel = [];
        foreach ($feeRows as $fee) {
            $byLabel[$fee['label'] ?? ''] = $fee;
        }

        $out = [];
        foreach ($labels as $label) {
            if (isset($byLabel[$label], self::CHARGE_TYPE_LABELS[$label])) {
                $out[] = $byLabel[$label];
            }
        }

        return $out;
    }

    /**
     * Schedule rows (installments/balloons) plus the fall-back balance row.
     */
    private function buildRemainderRows(PropertyOwnershipPaymentPlan $snapshot, array $data, float $finalPrice, int $baseSequence, Carbon $anchor): array
    {
        $rows = [];
        $sequence = $baseSequence;

        if ($snapshot->with_installment) {
            $installment = $data['installment_rows'][0] ?? null;
            $schedule = $installment['schedule'] ?? [];
            $frequency = $snapshot->installment_frequency ?: 'monthly';
            $monthsPerPeriod = self::monthsPerPeriod($frequency);
            $period = 0;

            foreach ($schedule as $item) {
                $period++;
                $isBalloon = ($item['type'] ?? '') === 'balloon';
                $sequence++;

                $rows[] = array_merge($this->baseRow(
                    $isBalloon ? 'balloon' : 'installment',
                    $isBalloon
                        ? 'Balloon #'.($item['balloon_no'] ?? $period)
                        : 'Installment #'.($item['inst_no'] ?? $period),
                    (float) ($item['amount'] ?? 0),
                    $sequence,
                    $anchor->copy()->addMonths($period * $monthsPerPeriod)
                ), [
                    'installment_no' => $isBalloon ? null : ($item['inst_no'] ?? $period),
                    'balloon_no' => $isBalloon ? ($item['balloon_no'] ?? $period) : null,
                ]);
            }
        }

        // When there is no installment plan, the residual (gross - included
        // fees) is billed as a single lump sum so SUM(ledger) === final_price.
        if (count($rows) === 0) {
            $sum = self::round2(collect($data['fee_rows'] ?? [])
                ->reject(fn ($f) => (($f['label'] ?? '') === 'Listed Price'))
                ->sum(fn ($f) => (float) ($f['amount'] ?? 0)));
            $balance = self::round2($finalPrice - $sum);
            $sequence++;

            if ($balance > 0) {
                $rows[] = $this->baseRow('balance', 'Balance Amount', $balance, $sequence, $anchor->copy()->addMonths(3));
            }
        }

        return $rows;
    }

    /**
     * Adjust the last emitted row so the ledger total equals final_price.
     */
    private function balanceTo(array &$rows, float $finalPrice): void
    {
        if (count($rows) === 0) {
            return;
        }

        $last = count($rows) - 1;
        $currentTotal = self::round2(collect($rows)->sum(fn ($r) => (float) $r['amount_due']));
        $diff = self::round2($finalPrice - $currentTotal);

        $rows[$last]['amount_due'] = $newAmount = self::round2((float) $rows[$last]['amount_due'] + $diff);
        $rows[$last]['remaining_balance'] = $newAmount;
    }

    private function baseRow(string $chargeType, string $displayName, float $amount, int $sequence, ?Carbon $dueDate): array
    {
        return [
            'charge_type' => $chargeType,
            'display_name' => $displayName,
            'amount_due' => self::round2($amount),
            'amount_paid' => 0,
            'remaining_balance' => self::round2($amount),
            'sequence_order' => $sequence,
            'due_date' => $dueDate,
            'payment_status' => 'pending',
        ];
    }

    public static function chargeLabel(string $chargeType): string
    {
        return match ($chargeType) {
            'booking_fee' => 'Booking Fee',
            'development_fee' => 'Development Fee',
            'down_payment' => 'Down Payment',
            'transfer_fee' => 'Transfer Fee',
            'confirmation_fee' => 'Confirmation Fee',
            'possession_fee' => 'Possession Fee',
            'installment' => 'Installment',
            'balloon' => 'Balloon',
            'balance' => 'Balance Amount',
            default => ucwords(str_replace('_', ' ', $chargeType)),
        };
    }

    public static function monthsPerPeriod(string $frequency): int
    {
        return match ($frequency) {
            'quarterly' => 3,
            'biannually' => 6,
            'annually' => 12,
            default => 1,
        };
    }

    public static function round2(float $value): float
    {
        return round((float) $value, 2);
    }
}
