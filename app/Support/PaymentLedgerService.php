<?php

namespace App\Support;

use App\Models\PaymentLateFee;
use App\Models\PaymentTransaction;
use App\Models\PaymentTransactionAllocation;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
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
        'Confirmation Fee' => 'confirmation_fee',
        'Possession Fee' => 'possession_fee',
    ];

    public function createLedgerFromSnapshot(PropertyReservedPaymentPlan $snapshot, bool $force = false, ?Carbon $anchor = null): void
    {
        $exists = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->exists();

        if ($exists && ! $force) {
            return;
        }
        if ($force && $snapshot->hasLivePayments()) {
            throw new \LogicException('Ledger cannot be rebuilt once payments are recorded.');
        }

        if ($force) {
            // Rows that were ever used by a payment must stay behind as
            // soft-deleted history: their allocations are the audit trail of
            // receipts that were later un-paid. Only unused rows are removed.
            PropertyPaymentLedger::query()
                ->where('ownership_payment_plan_id', $snapshot->id)
                ->get()
                ->each(function (PropertyPaymentLedger $row) {
                    if ($row->allAllocations()->exists()) {
                        $row->delete();
                    } else {
                        $row->forceDelete();
                    }
                });
        }

        // A transfer plan carries only the society transfer fee: exactly one
        // charge, due on the day the transfer was approved. No other fees, no
        // installments, no FBR tax.
        if ($snapshot->isTransfer()) {
            $due = $anchor ?? now()->startOfDay();
            $row = $this->baseRow('transfer_fee', 'Transfer Fee', (float) ($snapshot->final_price ?? 0), 1, $due);
            $row['ownership_payment_plan_id'] = $snapshot->id;
            PropertyPaymentLedger::create($row);

            return;
        }

        $data = PaymentPlanPresenter::buildFromSnapshot($snapshot);
        $finalPrice = self::round2((float) ($data['final_price'] ?? 0));

        $hasSchedule = (bool) $snapshot->with_installment && (int) ($snapshot->number_of_installments ?? 0) > 0;

        $rows = [];
        $sequence = 0;

        $anchor ??= $snapshot->propertyOwner?->started_at ? Carbon::parse($snapshot->propertyOwner->started_at) : now()->startOfDay();

        // 1) Up-front charges (booking + confirmation fee) due immediately.
        foreach ($this->feeRowsByLabel($data['fee_rows'] ?? [], ['Booking Fee', 'Confirmation Fee']) as $fee) {
            $rows[] = $this->rowForCharge(self::CHARGE_TYPE_LABELS[$fee['label']], $fee['amount'], $anchor, ++$sequence, $anchor);
        }

        // 2) Core charges (development / down payment) on their own
        //    due dates. Excluded fees are billed separately here because they are
        //    no longer part of the installment schedule's grand total.
        foreach ($this->feeRowsByLabel($data['fee_rows'] ?? [], ['Development Fee', 'Down Payment']) as $fee) {
            $rows[] = $this->rowForCharge(
                self::CHARGE_TYPE_LABELS[$fee['label']],
                $fee['amount'],
                $anchor,
                ++$sequence
            );
        }

        // 3) Installment schedule (or the balance lump when none exists).
        $rows = array_merge($rows, $this->buildRemainderRows($snapshot, $data, $finalPrice, $sequence, $anchor));

        // 4) Possession fee - set due date per requirements
        $possession = $this->feeRowsByLabel($data['fee_rows'] ?? [], ['Possession Fee']);
        if ($possession) {
            $hasInstallments = ($snapshot->with_installment && (int) ($snapshot->number_of_installments ?? 0) > 0);
            if ($hasInstallments) {
                $latestDue = null;
                foreach ($rows as $r) {
                    if ($r['due_date'] instanceof Carbon) {
                        $latestDue = ($latestDue === null || $r['due_date']->gt($latestDue)) ? $r['due_date'] : $latestDue;
                    }
                }
                $dueDate = $latestDue ? $latestDue->copy() : $anchor->copy();
                $rows[] = $this->rowForCharge('possession_fee', $possession[0]['amount'], $anchor, count($rows) + 1, $dueDate);
            } else {
                $rows[] = $this->rowForCharge('possession_fee', $possession[0]['amount'], $anchor, count($rows) + 1, $anchor->copy());
            }
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
     * outstanding rows in sequence order.
     *
     * $targetLedgerIds restricts the cascade to the given charges. Pass null
     * (or an empty array) to spread the amount over every outstanding charge,
     * which is the "By Amount" mode. An array of ids is the "By Charges" mode,
     * where the same cascade runs but only against the selected rows.
     *
     * @throws \InvalidArgumentException when the target is invalid or the amount exceeds the targeted balance
     */
    public function applyPayment(PropertyReservedPaymentPlan $snapshot, array $data, ?array $targetLedgerIds = null): PaymentTransaction
    {
        $amount = self::round2((float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        // A completed / reversed / cancelled plan is closed for new payments —
        // the read-only views hide the form, and this blocks direct posts.
        if (in_array($snapshot->status, ['completed', 'reversed', 'cancelled'], true)) {
            throw new \InvalidArgumentException(
                $snapshot->status === 'completed'
                  ? 'This payment plan is already fully paid — no further payments can be recorded.'
                  : 'This payment plan is '.$snapshot->status.' — no further payments can be recorded.'
            );
        }

        $paidAt = isset($data['paid_at']) && $data['paid_at'] !== '' && $data['paid_at'] !== null
          ? Carbon::parse($data['paid_at'])
          : now();

        $ids = is_array($targetLedgerIds)
          ? array_values(array_unique(array_filter(array_map('intval', $targetLedgerIds))))
          : [];

        $rows = $this->allocationRows($snapshot, $ids);

        $outstanding = self::round2($rows->sum(fn ($r) => (float) $r->remaining_balance));

        if ($amount > $outstanding) {
            throw new \InvalidArgumentException(
                $ids === [] ? 'Payment exceeds the outstanding balance.' : 'Payment exceeds the total of the selected charges.'
            );
        }

        return DB::transaction(function () use ($snapshot, $rows, $amount, $paidAt, $data) {
            $allocations = $this->planAllocations($rows, $amount);

            foreach ($allocations as $alloc) {
                $row = $alloc['ledger'];
                $newRemaining = $alloc['remaining_balance'];

                $row->forceFill([
                    'amount_paid' => self::round2((float) $row->amount_paid + $alloc['amount']),
                    'remaining_balance' => $newRemaining,
                ]);

                if ($newRemaining <= 0) {
                    $row->forceFill([
                        'payment_status' => 'paid',
                        'paid_at' => $paidAt,
                    ]);
                }

                $row->save();
            }

            $chargeSubtotal = self::round2(collect($allocations)->sum(fn ($a) => $a['amount']));

            // FBR tax is collected on top of the charges and is linear in the
            // amount, so a plan paid in one transaction or in many totals the
            // same tax. The headroom cap absorbs per-transaction rounding, and
            // the settling payment absorbs the residual so the plan-wide total
            // lands exactly on the target.
            $settlesPlan = $this->outstandingBalance($snapshot) <= 0.004;
            $tax = $this->taxForChargeSubtotal($snapshot, $chargeSubtotal, $settlesPlan);

            $taxAmount = $tax['tax'];
            $taxRate = $tax['rate'];
            $taxCapped = $tax['capped'];

            // Late fees are calculated from the same allocation drafts the
            // ledger was updated with, so the preview and the recorded payment
            // can never disagree.
            $lateFee = app(LateFeeService::class)->calculate($snapshot, $allocations, $paidAt);
            $lateFeeAmount = self::round2((float) $lateFee['total']);

            $transaction = PaymentTransaction::create([
                'ownership_payment_plan_id' => $snapshot->id,
                'charge_subtotal' => $chargeSubtotal,
                'tax_amount' => $taxAmount,
                'late_fee_amount' => $lateFeeAmount,
                'tax_rate_percent' => $taxRate,
                'tax_capped' => $taxCapped,
                'amount_paid' => self::round2($chargeSubtotal + $taxAmount + $lateFeeAmount),
                'payment_method' => $data['payment_method'] ?? 'other',
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'] ?? null,
                'recorded_by_user_id' => Auth::id(),
                'paid_at' => $paidAt,
            ]);

            // The transaction stores the aggregate tax; each lined allocation
            // stores its own proportional share so un-paying a charge can
            // refund the tax it carried. splitTax absorbs the rounding
            // residual, so the shares always sum back to the transaction tax.
            $taxShares = $this->splitTax(
                $taxAmount,
                collect($allocations)->map(fn ($a) => (float) $a['amount'])->all()
            );

            foreach ($allocations as $i => $alloc) {
                PaymentTransactionAllocation::create([
                    'payment_transaction_id' => $transaction->id,
                    'property_payment_ledger_id' => $alloc['ledger']->id,
                    'amount_allocated' => $alloc['amount'],
                    'tax_amount' => $taxShares[$i] ?? 0.0,
                ]);
            }

            foreach ($lateFee['charges'] as $charge) {
                PaymentLateFee::create([
                    'property_payment_ledger_id' => $charge['property_payment_ledger_id'],
                    'payment_transaction_id' => $transaction->id,
                    'late_fee_rule_id' => $charge['late_fee_rule_id'],
                    'fine_type' => $charge['fine_type'],
                    'fine_value' => $charge['fine_value'],
                    'rule_grace_days' => $charge['rule_grace_days'],
                    'days_late' => $charge['days_late'],
                    'base_amount' => $charge['base_amount'],
                    'fee_amount' => $charge['fee_amount'],
                    'status' => PaymentLateFee::STATUS_COLLECTED,
                ]);
            }

            if ($this->summary($snapshot)['remaining'] <= 0) {
                $snapshot->update(['status' => 'completed']);

                // A fully-paid ownership plan closes its sale, so the listing is
                // taken down: the property is no longer for sale and is now
                // eligible for transfer.
                $snapshot->propertyOwnerable?->listing()?->update(['closed' => true]);
            }

            return $transaction;
        });
    }

    /**
     * The charged rows a payment can be applied to: the selected charges, or
     * every outstanding charge in sequence order ("By Amount" mode).
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Support\Collection<int, PropertyPaymentLedger>
     */
    public function allocationRows(PropertyReservedPaymentPlan $snapshot, array $ids = [])
    {
        $rowsQuery = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id);

        if ($ids !== []) {
            $rows = $rowsQuery->whereIn('id', $ids)
                ->orderBy('sequence_order')
                ->get();

            if ($rows->count() !== count($ids)) {
                throw new \InvalidArgumentException('One or more selected charges do not belong to this payment plan.');
            }
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

        $outstanding = self::round2($rows->sum(fn ($r) => (float) $r->remaining_balance));

        if ($outstanding <= 0) {
            throw new \InvalidArgumentException(
                $ids === [] ? 'Nothing outstanding to pay.' : 'The selected charges are already settled.'
            );
        }

        return $rows;
    }

    /**
     * Spread an amount over the given ledger rows (oldest first) WITHOUT
     * writing anything. Used by both payment recording and the live preview.
     *
     * Each draft carries the row, the amount allocated to it, the balance that
     * would remain, and whether the allocation would clear the charge.
     *
     * @param  iterable<int, PropertyPaymentLedger>  $rows
     * @return list<array{ledger: PropertyPaymentLedger, amount: float, remaining_balance: float, settles_row: bool}>
     */
    public function planAllocations(iterable $rows, float $amount): array
    {
        $remainingToAllocate = self::round2($amount);
        $allocations = [];

        foreach ($rows as $row) {
            if ($remainingToAllocate <= 0.004) {
                break;
            }

            $rowRemaining = self::round2((float) $row->remaining_balance);

            if ($rowRemaining <= 0.004) {
                continue;
            }

            $alloc = self::round2(min($remainingToAllocate, $rowRemaining));

            if ($alloc <= 0) {
                continue;
            }

            $newRemaining = self::round2($rowRemaining - $alloc);

            $allocations[] = [
                'ledger' => $row,
                'amount' => $alloc,
                'remaining_balance' => $newRemaining,
                'settles_row' => $newRemaining <= 0,
            ];

            $remainingToAllocate = self::round2($remainingToAllocate - $alloc);
        }

        return $allocations;
    }

    /**
     * Compute a payment preview WITHOUT writing anything: the allocation
     * drafts, FBR tax, late fees and the amount that would be collected.
     *
     * @param  list<int>  $targetLedgerIds
     * @return array<string, mixed>
     */
    public function previewPayment(PropertyReservedPaymentPlan $snapshot, array $data, ?array $targetLedgerIds = null): array
    {
        $ids = is_array($targetLedgerIds)
          ? array_values(array_unique(array_filter(array_map('intval', $targetLedgerIds))))
          : [];

        $rows = $this->allocationRows($snapshot, $ids);

        $amount = self::round2((float) ($data['amount'] ?? 0));

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Enter an amount greater than zero.');
        }

        $outstanding = self::round2($rows->sum(fn ($r) => (float) $r->remaining_balance));

        if ($amount > $outstanding) {
            throw new \InvalidArgumentException(
                $ids === [] ? 'Payment exceeds the outstanding balance.' : 'Payment exceeds the total of the selected charges.'
            );
        }

        $allocations = $this->planAllocations($rows, $amount);

        $chargeSubtotal = self::round2(collect($allocations)->sum(fn ($a) => $a['amount']));

        $settlesPlan = self::round2($this->outstandingBalance($snapshot) - $chargeSubtotal) <= 0.004;
        $tax = $this->taxForChargeSubtotal($snapshot, $chargeSubtotal, $settlesPlan);

        $paidAt = $data['paid_at'] ?? null;
        $lateFee = app(LateFeeService::class)->calculate($snapshot, $allocations, $paidAt);

        return [
            'charges' => collect($allocations)->map(fn ($a) => [
                'ledger_id' => $a['ledger']->id,
                'display_name' => $a['ledger']->display_name,
                'amount' => $a['amount'],
            ])->values()->all(),
            'charge_subtotal' => $chargeSubtotal,
            'tax_amount' => $tax['tax'],
            'tax_rate_percent' => $tax['rate'],
            'tax_capped' => $tax['capped'],
            'late_fees' => $lateFee['charges'],
            'late_fee_amount' => $lateFee['total'],
            'amount_paid' => self::round2($chargeSubtotal + $tax['tax'] + $lateFee['total']),
        ];
    }

    /**
     * FBR tax carried by one payment, capped by the plan-wide target.
     *
     * @return array{tax: float, rate: float, capped: bool}
     */
    private function taxForChargeSubtotal(PropertyReservedPaymentPlan $snapshot, float $chargeSubtotal, bool $settlesPlan = false): array
    {
        $rate = $snapshot->fbrTaxRate();

        if ($rate <= 0 || $chargeSubtotal <= 0) {
            return ['tax' => 0.0, 'rate' => $rate, 'capped' => false];
        }

        // Locked so two concurrent payments cannot both spend the same headroom.
        // Reversed transactions no longer count - their tax headroom is freed.
        $alreadyCollected = PaymentTransaction::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->where('status', 'paid')
            ->lockForUpdate()
            ->get(['tax_amount'])
            ->sum(fn ($t) => (float) $t->tax_amount);

        $result = $this->taxCalculator()->taxForTransaction(
            $chargeSubtotal,
            $rate,
            self::round2($alreadyCollected),
            $this->taxBasePrice($snapshot),
            $settlesPlan
        );

        return ['tax' => $result['tax'], 'rate' => $rate, 'capped' => $result['capped']];
    }

    /**
     * Charges still outstanding across the whole plan, not just the cascade
     * touched by this payment.
     */
    private function outstandingBalance(PropertyReservedPaymentPlan $snapshot): float
    {
        return self::round2(PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->sum('remaining_balance'));
    }

    /**
     * The price the FBR slab is matched against.
     *
     * Prefers the stored snapshot column; falls back to the presenter for
     * legacy rows committed before the column was populated.
     */
    private function taxBasePrice(PropertyReservedPaymentPlan $snapshot): float
    {
        $price = self::round2((float) ($snapshot->final_price ?? 0));

        if ($price > 0) {
            return $price;
        }

        return self::round2((float) (PaymentPlanPresenter::buildFromSnapshot($snapshot)['final_price'] ?? 0));
    }

    private function taxCalculator(): FbrTaxCalculator
    {
        return app(FbrTaxCalculator::class);
    }

    /**
     * How each allocation contributes to a transaction's tax.
     *
     * Prefers the per-allocation tax_amount stored at payment time - exact for
     * reversal refunds and immune to slab edits later. Falls back to splitting
     * the aggregate proportionally for rows recorded before the column existed
     * (their stored values are all zero, so the stored sum cannot match the
     * transaction tax).
     *
     * @param  iterable<int, \App\Models\PaymentTransactionAllocation>  $allocations
     * @return array<int, float>
     */
    public function allocationTaxShares(iterable $allocations, float $tax): array
    {
        $stored = [];
        foreach ($allocations as $alloc) {
            $stored[] = self::round2((float) ($alloc->tax_amount ?? 0));
        }

        if ($stored !== [] && abs(array_sum($stored) - self::round2($tax)) <= 0.05) {
            return $stored;
        }

        $amounts = [];
        foreach ($allocations as $alloc) {
            $amounts[] = (float) ($alloc->amount_allocated ?? 0);
        }

        return $this->splitTax($tax, $amounts);
    }

    /**
     * Split a tax amount fairly across allocated charge amounts.
     *
     * Every part gets its proportional share, rounded to the paisa, and the
     * rounding residual (outside the last-paisa boundary) is moved onto the
     * largest charge so the parts always sum exactly back to $tax.
     *
     * @param  array<int, float>  $amounts
     * @return array<int, float>
     */
    public function splitTax(float $tax, array $amounts): array
    {
        $amounts = array_values(array_map('floatval', $amounts));
        $count = count($amounts);

        if ($count === 0) {
            return [];
        }
        if ($count === 1) {
            return [self::round2($tax)];
        }

        $total = array_sum($amounts);
        if ($total <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $tax = self::round2($tax);
        $shares = array_map(fn ($a) => self::round2($tax * ($a / $total)), $amounts);
        $residual = self::round2($tax - array_sum($shares));

        if ($residual != 0.0) {
            $largest = (int) array_search(max($amounts), $amounts);
            $shares[$largest] = self::round2($shares[$largest] + $residual);
        }

        return $shares;
    }

    /**
     * The date-based status rules (only PAID is balance-based).
     *
     * Unpaid charges that have fallen past their due date become "late" once
     * the society's minimum late-fee grace period is crossed. Within that grace
     * period they stay "current_due". Any unpaid charge whose due date is the
     * next outstanding date (or today) is labelled "current_due" so the row the
     * owner should pay now is always shown as current due.
     */
    public function determineStatus(PropertyPaymentLedger $row, ?Carbon $today = null, ?Carbon $nextDue = null, ?int $graceDays = null): string
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
            $daysOverdue = (int) $due->diffInDays($today);
            $grace = $graceDays ?? 7;

            return $daysOverdue <= $grace ? 'current_due' : 'late';
        }

        if ($nextDue !== null && $due->equalTo($nextDue)) {
            return 'current_due';
        }

        return 'pending';
    }

    /**
     * Reverse a payment transaction: restore each affected ledger row's
     * balance and soft-delete the allocations. The transaction itself is
     * NEVER deleted - it is flipped to "returned" and stays auditable.
     *
     * Options:
     *  - status        resulting transaction status (default "returned")
     *  - ledger_status resulting payment_status for the touched ledger rows
     *                  ("pending" reopens them for payment by default; a
     *                  cancellation passes "reversed" to close them for good)
     */
    public function reversePayment(PaymentTransaction $transaction, array $reversal = []): void
    {
        DB::transaction(function () use ($transaction, $reversal) {
            $snapshot = $transaction->ownershipPlan;
            $ledgerStatus = $reversal['ledger_status'] ?? 'pending';

            // Cancellation keeps allocations as history with a "reversed" status;
            // a plain reversal removes them (soft delete) as before.
            $keepAsReversed = $ledgerStatus === 'reversed';

            $allocations = $transaction->allocations()
                ->where('status', 'active')   // never re-process already reversed rows
                ->with('ledgerRow')
                ->get();

            foreach ($allocations as $alloc) {
                $row = $alloc->ledgerRow;

                if ($row) {
                    $newPaid = self::round2((float) $row->amount_paid - (float) $alloc->amount_allocated);
                    $row->forceFill([
                        'amount_paid' => max(0, $newPaid),
                        'remaining_balance' => self::round2((float) $row->remaining_balance + (float) $alloc->amount_allocated),
                        'payment_status' => $ledgerStatus,
                        'paid_at' => null,
                    ]);
                    $row->save();
                }

                if ($keepAsReversed) {
                    $alloc->forceFill(['status' => 'reversed'])->save();
                } else {
                    $alloc->delete();
                }
            }

            $transaction->forceFill([
                'status' => $reversal['status'] ?? 'returned',
            ])->save();

            if ($snapshot && $snapshot->status === 'completed') {
                $snapshot->update(['status' => 'active']);
            }
            $this->refreshStatuses($snapshot);
        });
    }

    /**
     * Reverse selected charges out of ONE payment transaction.
     *
     * A single bulk payment can settle several charges at once, so reversing a
     * charge must not destroy the charges it was paid alongside. Only the given
     * allocations are removed and the transaction is re-priced around what is
     * left; it is deleted only when the reversal empties it completely, which
     * is exactly what reversePayment() would have done to the whole payment.
     *
     * @param  list<int>  $allocationIds
     * @return array{removed_all: bool, released: float, transaction: ?PaymentTransaction}
     *
     * @throws \InvalidArgumentException when no charges are given, or one of them does not belong to this payment
     */
    public function reverseAllocations(PaymentTransaction $transaction, array $allocationIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $allocationIds))));

        if ($ids === []) {
            throw new \InvalidArgumentException('Select at least one charge to reverse.');
        }

        $owned = $transaction->allocations()->whereIn('id', $ids)->count();

        if ($owned !== count($ids)) {
            throw new \InvalidArgumentException('One or more selected charges do not belong to this payment.');
        }

        return DB::transaction(function () use ($transaction, $ids) {
            $snapshot = $transaction->ownershipPlan;

            // Tax released is the transaction's own tax apportioned across its
            // charges, so the surviving tax is the released figure removed from
            // the total - no rounding drift, and never more than was collected.
            $all = $transaction->allocations()->orderBy('id')->get();
            $shares = $this->allocationTaxShares($all, (float) $transaction->tax_amount);

            $released = 0.0;

            foreach ($all as $i => $alloc) {
                if (! in_array((int) $alloc->id, $ids, true)) {
                    continue;
                }

                $released = self::round2($released + ($shares[$i] ?? 0.0));
                $this->reopenLedgerRow($alloc->ledgerRow, (float) $alloc->amount_allocated);

                // Kept as a history record (soft delete), never hard-deleted.
                $alloc->delete();
            }

            if ($snapshot && $snapshot->status === 'completed') {
                $snapshot->update(['status' => 'active']);
            }

            // Late fees are non-refundable: reversing charges never removes or
            // reduces the fee that was collected when the payment was taken.
            $lateFee = self::round2((float) $transaction->late_fee_amount);

            if (! $transaction->allocations()->exists()) {
                // Every charge of this payment is gone, so the payment is
                // closed as returned - the record itself is never deleted.
                $transaction->forceFill([
                    'charge_subtotal' => 0,
                    'tax_amount' => 0,
                    'amount_paid' => $lateFee,
                    'status' => 'returned',
                ])->save();
                $this->refreshStatuses($snapshot);

                return ['removed_all' => true, 'released' => $released, 'transaction' => $transaction->fresh()];
            }

            $subtotal = self::round2((float) $transaction->allocations()->sum('amount_allocated'));
            $tax = max(0.0, self::round2((float) $transaction->tax_amount - $released));

            $transaction->forceFill([
                'charge_subtotal' => $subtotal,
                'tax_amount' => $tax,
                'amount_paid' => self::round2($subtotal + $tax + $lateFee),
            ])->save();

            $this->refreshStatuses($snapshot);

            return [
                'removed_all' => false,
                'released' => $released,
                'transaction' => $transaction->fresh(),
            ];
        });
    }

    /**
     * Reverse a set of charges chosen in the ledger, wherever they were paid.
     *
     * The selection is by CHARGE rather than by payment, so un-paying one charge
     * of a bulk payment leaves the charges paid alongside it settled, and a
     * selection spanning several payments is handled in one pass. A payment is
     * only deleted when the reversal takes every one of its charges away.
     *
     * @param  iterable<int, PropertyPaymentLedger>  $rows
     * @return array{rows: list<array>, transactions: int, removed: int, tax_released: float}
     *
     * @throws \InvalidArgumentException when nothing is selected, or a selected charge has no payment recorded
     */
    public function reverseCharges(iterable $rows): array
    {
        $rowIds = collect($rows)->pluck('id')->map('intval')->unique()->values()->all();

        if ($rowIds === []) {
            throw new \InvalidArgumentException('Select at least one charge to reverse.');
        }
        $allocations = PaymentTransactionAllocation::query()
            ->where('status', 'active')
            ->whereIn('property_payment_ledger_id', $rowIds)
            ->get(['id', 'payment_transaction_id', 'property_payment_ledger_id']);

        if ($allocations->isEmpty()) {
            throw new \InvalidArgumentException('The selected charges have no recorded payment to reverse.');
        }

        $removed = 0;
        $taxReleased = 0.0;

        DB::transaction(function () use ($allocations, &$removed, &$taxReleased) {
            foreach ($allocations->groupBy('payment_transaction_id') as $group) {
                $transaction = PaymentTransaction::query()->find($group->first()->payment_transaction_id);

                if (! $transaction) {
                    continue;
                }

                $outcome = $this->reverseAllocations($transaction, $group->pluck('id')->all());
                $taxReleased = self::round2($taxReleased + ($outcome['released'] ?? 0.0));

                if ($outcome['removed_all']) {
                    $removed++;
                }
            }
        });

        return [
            'rows' => PropertyPaymentLedger::query()
                ->whereIn('id', $rowIds)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'uuid' => $r->uuid,
                    'amount_paid' => (float) $r->amount_paid,
                    'remaining_balance' => (float) $r->remaining_balance,
                    'payment_status' => $r->payment_status,
                ])->values()->all(),
            'transactions' => $allocations->pluck('payment_transaction_id')->unique()->count(),
            'removed' => $removed,
            'tax_released' => $taxReleased,
        ];
    }

    /**
     * Give a charge back its outstanding balance after a reversal.
     *
     * The settled marker is only dropped when the charge is no longer fully
     * covered, so a charge paid by two payments stays paid while the other
     * allocation survives.
     */
    private function reopenLedgerRow(?PropertyPaymentLedger $row, float $amount): void
    {
        if (! $row) {
            return;
        }

        $remaining = self::round2((float) $row->remaining_balance + $amount);

        $row->forceFill([
            'amount_paid' => max(0.0, self::round2((float) $row->amount_paid - $amount)),
            'remaining_balance' => $remaining,
        ]);

        if ($remaining > 0.004) {
            $row->forceFill([
                'payment_status' => 'pending',
                'paid_at' => null,
            ]);
        }

        $row->save();
    }

    /**
     * Re-run determineStatus() over one plan (or all plans with a ledger).
     */
    public function refreshStatuses(?PropertyReservedPaymentPlan $snapshot = null): void
    {
        $plans = $snapshot
          ? collect([$snapshot])
          : PropertyReservedPaymentPlan::query()->whereHas('ledgerRows')->get();

        foreach ($plans as $plan) {
            $rows = PropertyPaymentLedger::query()
                ->where('ownership_payment_plan_id', $plan->id)
                ->get();

            $graceDays = app(LateFeeService::class)->minimumGraceDaysForPlan($plan);

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
                // A cancelled reservation closes its charges for good - they
                // must never be recomputed back to a payable status.
                if ($row->payment_status === 'reversed') {
                    continue;
                }

                $status = $this->determineStatus($row, null, $nextDue, $graceDays);
                if ($row->payment_status !== $status) {
                    $row->update(['payment_status' => $status]);
                }
            }
        }
    }

    public function summary(PropertyReservedPaymentPlan $snapshot): array
    {
        $rows = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->get();

        $graceDays = app(LateFeeService::class)->minimumGraceDaysForPlan($snapshot);

        $nextDue = null;
        foreach ($rows as $row) {
            // Closed charges of a cancelled reservation are out of the plan:
            // no due date, no overdue count.
            if ($row->payment_status === 'reversed') {
                continue;
            }
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
        $byStatus = ['pending' => 0, 'current_due' => 0, 'late' => 0, 'paid' => 0, 'reversed' => 0];
        $overdueDays = 0;

        foreach ($rows as $row) {
            $totalDue += (float) $row->amount_due;
            $totalPaid += (float) $row->amount_paid;

            if ($row->payment_status === 'reversed') {
                $byStatus['reversed']++;
                $row->payment_status = 'reversed';

                continue;
            }

            $status = $this->determineStatus($row, null, $nextDue, $graceDays);
            $byStatus[$status]++;
            $row->payment_status = $status;

            if (! $row->isPaid() && $row->due_date) {
                $due = $row->due_date->copy()->startOfDay();
                if ($due->lessThan(now()->startOfDay())) {
                    $overdueDays = max($overdueDays, (int) $due->diffInDays(now()->startOfDay()));
                }
            }
        }

        $totalDue = self::round2($totalDue);
        $totalPaid = self::round2($totalPaid);

        // Tax is collected on top of the charges, so cash received exceeds the
        // ledger's paid total by exactly the FBR tax collected so far.
        $taxCollected = self::round2(PaymentTransaction::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->where('status', 'paid')
            ->sum('tax_amount'));

        // Late fees are non-refundable, so collected fees are read from the
        // transactions themselves (they survive a later reversal of the charge).
        $lateFeeCollected = self::round2(PaymentTransaction::query()
            ->where('ownership_payment_plan_id', $snapshot->id)
            ->sum('late_fee_amount'));

        $taxRate = (float) ($snapshot->fbr_tax_rate_percent ?? 0);

        return [
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'remaining' => self::round2($totalDue - $totalPaid),
            'tax_rate_percent' => $taxRate,
            'tax_collected' => $taxCollected,
            'tax_total_due' => $this->taxCalculator()->planTaxTotal($totalDue, $taxRate),
            'tax_remaining' => self::round2($this->taxCalculator()->planTaxTotal($totalDue, $taxRate) - $taxCollected),
            'late_fee_collected' => $lateFeeCollected,
            'total_received' => self::round2($totalPaid + $taxCollected + $lateFeeCollected),
            'by_status' => $byStatus,
            'next_due_date' => $nextDue,
            'overdue_days' => $overdueDays,
            'paid_percent' => $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 1) : 0,
        ];
    }

    /**
     * Delete + recreate the ledger. Only allowed while no payments exist.
     */
    public function rebuildFromSnapshot(PropertyReservedPaymentPlan $snapshot, ?Carbon $anchor = null): void
    {
        if ($snapshot->isTransfer()) {
            throw new \LogicException('A transfer-fee plan has a fixed single charge and cannot be rebuilt.');
        }

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
    private function buildRemainderRows(PropertyReservedPaymentPlan $snapshot, array $data, float $finalPrice, int $baseSequence, Carbon $anchor): array
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
        // charges) is billed as a single lump sum so SUM(ledger) === final_price.
        // Every charge of an un-installmented plan is due on the same day, so
        // the remaining amount is due alongside the other charges.
        if (count($rows) === 0) {
            $sum = self::round2(collect($data['fee_rows'] ?? [])
                ->reject(fn ($f) => (($f['label'] ?? '') === 'Listed Price'))
                ->sum(fn ($f) => (float) ($f['amount'] ?? 0)));
            $balance = self::round2($finalPrice - $sum);
            $sequence++;

            if ($balance > 0) {
                $rows[] = $this->baseRow('balance', 'Remaining Amount', $balance, $sequence, $anchor->copy());
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

            'confirmation_fee' => 'Confirmation Fee',
            'possession_fee' => 'Possession Fee',
            'installment' => 'Installment',
            'balloon' => 'Balloon',
            'balance' => 'Remaining Amount',
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
