<?php

namespace App\Http\Controllers;

use App\Models\PaymentTransaction;
use App\Models\PropertyOwnershipPaymentPlan;
use App\Models\PropertyPaymentLedger;
use App\Support\PaymentLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentTransactionController extends Controller
{
    /**
     * Record a payment (full, partial or multi-charge) against a committed
     * ownership plan's ledger.
     */
    public function store(Request $request, PropertyOwnershipPaymentPlan $snapshot)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,cheque,bank_transfer,card,mobile_wallet,adjustment,waiver,other',
            'paid_at' => 'nullable|date',
            'reference_no' => 'nullable|required_unless:payment_method,cash|string|max:255',
            'description' => 'nullable|string|max:1000',
            'ledger_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Please check the payment form: '.collect($validator->errors()->all())->implode(' '));
        }

        try {
            $transaction = app(PaymentLedgerService::class)->applyPayment(
                $snapshot,
                $request->all(),
                $request->filled('ledger_id') ? (int) $request->input('ledger_id') : null
            );

            return redirect()->back()->with(
                'success',
                'Payment of '.number_format((float) $request->input('amount'), 2).' '.($snapshot->price_unit ?? 'PKR').' recorded (Ref '.($request->input('reference_no') ?: $transaction->uuid).').'
            );
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Generate (or regenerate) the full payment ledger of a committed plan —
     * all charges and their due dates. Rebuilds only while no payments exist.
     */
    public function initiate(Request $request, PropertyOwnershipPaymentPlan $snapshot)
    {
        try {
            $service = app(PaymentLedgerService::class);

            $anchor = $request->filled('initiate_at')
                ? \Illuminate\Support\Carbon::parse($request->input('initiate_at'))
                : null;

            if (! $snapshot->ledgerRows()->exists()) {
                $service->createLedgerFromSnapshot($snapshot, force: false, anchor: $anchor);
                $message = 'Payments initiated — all charges and due dates have been generated.';
            } elseif ($snapshot->paymentTransactions()->exists()) {
                return redirect()->back()
                    ->with('error', 'Payments have already been recorded for this plan — the ledger cannot be re-initiated.');
            } else {
                $service->rebuildFromSnapshot($snapshot, $anchor);
                $message = 'Payment charges re-initiated with updated due dates.';
            }

            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverse a payment recorded against a single ledger charge. Returns JSON
     * so the payments section can update in place without a full page reload.
     */
    public function unpay(Request $request, PropertyPaymentLedger $row)
    {
        try {
            $txns = PaymentTransaction::query()
                ->whereHas('allocations', fn ($q) => $q->where('property_payment_ledger_id', $row->id))
                ->orderByDesc('paid_at')->orderByDesc('id')
                ->get();

            if ($txns->isEmpty()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No payments found to reverse for this charge.',
                ], 422);
            }

            $service = app(PaymentLedgerService::class);
            foreach ($txns as $txn) {
                $service->reversePayment($txn);
            }

            $plan = $row->ownershipPlan;
            if (! $plan) {
                return response()->json([
                    'ok' => false,
                    'message' => 'This charge is not linked to a payment plan.',
                ], 422);
            }

            $service->refreshStatuses($plan);
            $row->refresh();
            $summary = $service->summary($plan);

            return response()->json([
                'ok' => true,
                'message' => 'Payment reversed for '.$row->display_name.'.',
                'row' => [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'amount_paid' => (float) $row->amount_paid,
                    'remaining_balance' => (float) $row->remaining_balance,
                    'payment_status' => $row->payment_status,
                ],
                'summary' => [
                    'total_paid' => $summary['total_paid'],
                    'remaining' => $summary['remaining'],
                    'paid_percent' => $summary['paid_percent'],
                    'overdue' => $summary['by_status']['late'] + $summary['by_status']['current_due'],
                    'next_due_date' => $summary['next_due_date']?->format('d M Y') ?? '—',
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
