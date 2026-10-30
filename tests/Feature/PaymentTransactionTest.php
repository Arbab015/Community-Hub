<?php

namespace Tests\Feature;

use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentTransactionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_amount_mode_cascades_over_every_outstanding_charge_in_sequence_order(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000, 25_000]);

        $this->assertPaid($this->pay($plan, ['payment_mode' => 'amount', 'amount' => 120_000]));

        $first = $rows[0]->fresh();
        $this->assertSame(100_000.0, (float) $first->amount_paid);
        $this->assertSame(0.0, (float) $first->remaining_balance);
        $this->assertSame('paid', $first->payment_status);

        $second = $rows[1]->fresh();
        $this->assertSame(20_000.0, (float) $second->amount_paid);
        $this->assertSame(30_000.0, (float) $second->remaining_balance);
        $this->assertNotSame('paid', $second->payment_status);

        $third = $rows[2]->fresh();
        $this->assertSame(0.0, (float) $third->amount_paid);
        $this->assertSame(25_000.0, (float) $third->remaining_balance);
    }

    public function test_amount_mode_ignores_any_submitted_ledger_ids(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000, 25_000]);

        // The last charge is selected, but amount mode must still spread oldest-first.
        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'amount',
            'amount' => 100_000,
            'ledger_ids' => [$rows[2]->id],
        ]));

        $this->assertSame(0.0, (float) $rows[0]->fresh()->remaining_balance);
        $this->assertSame(50_000.0, (float) $rows[1]->fresh()->remaining_balance);
        $this->assertSame(25_000.0, (float) $rows[2]->fresh()->remaining_balance);
    }

    public function test_charges_mode_settles_only_the_selected_charges(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000, 25_000]);

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 125_000,
            'ledger_ids' => [$rows[0]->id, $rows[2]->id],
        ]));

        $this->assertSame(0.0, (float) $rows[0]->fresh()->remaining_balance);
        $this->assertSame('paid', $rows[0]->fresh()->payment_status);

        $this->assertSame(0.0, (float) $rows[2]->fresh()->remaining_balance);
        $this->assertSame('paid', $rows[2]->fresh()->payment_status);

        // The unselected middle charge is untouched.
        $this->assertSame(50_000.0, (float) $rows[1]->fresh()->remaining_balance);
        $this->assertSame(0.0, (float) $rows[1]->fresh()->amount_paid);
    }

    public function test_charges_mode_cascades_in_sequence_order_not_selection_order(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000, 25_000]);

        // Selected newest-first on purpose: allocation must still run oldest-first.
        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 120_000,
            'ledger_ids' => [$rows[2]->id, $rows[0]->id],
        ]));

        $this->assertSame(0.0, (float) $rows[0]->fresh()->remaining_balance);
        $this->assertSame(20_000.0, (float) $rows[2]->fresh()->amount_paid);
        $this->assertSame(5_000.0, (float) $rows[2]->fresh()->remaining_balance);
    }

    public function test_charges_mode_lets_the_user_pay_less_than_the_selected_total(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        // Selected total is 150,000 but only 120,000 is paid.
        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 120_000,
            'ledger_ids' => [$rows[0]->id, $rows[1]->id],
        ]));

        $first = $rows[0]->fresh();
        $this->assertSame(100_000.0, (float) $first->amount_paid);
        $this->assertSame(0.0, (float) $first->remaining_balance);
        $this->assertSame('paid', $first->payment_status);

        $second = $rows[1]->fresh();
        $this->assertSame(20_000.0, (float) $second->amount_paid);
        $this->assertSame(30_000.0, (float) $second->remaining_balance);
    }

    public function test_charges_mode_rejects_an_amount_larger_than_the_selected_total(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        $this->assertRejected($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 90_000,
            'ledger_ids' => [$rows[1]->id],
        ]));

        // Nothing may be allocated when the cap is breached.
        $this->assertSame(0.0, (float) $rows[0]->fresh()->amount_paid);
        $this->assertSame(0.0, (float) $rows[1]->fresh()->amount_paid);
        $this->assertSame(50_000.0, (float) $rows[1]->fresh()->remaining_balance);
        $this->assertSame(0, $plan->paymentTransactions()->count());
    }

    public function test_charges_mode_rejects_charges_belonging_to_another_plan(): void
    {
        [$plan] = $this->planWithCharges([100_000, 50_000]);
        [, $otherRows] = $this->planWithCharges([999_000]);

        $this->assertRejected($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 999_000,
            'ledger_ids' => [$otherRows[0]->id],
        ]));

        $this->assertSame(0, $plan->paymentTransactions()->count());
        $this->assertSame(0.0, (float) $otherRows[0]->fresh()->amount_paid);
    }

    public function test_charges_mode_rejects_charges_that_are_already_settled(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 100_000,
            'ledger_ids' => [$rows[0]->id],
        ]));

        $this->assertRejected($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 5_000,
            'ledger_ids' => [$rows[0]->id],
        ]));

        $this->assertSame(1, $plan->paymentTransactions()->count());
    }

    public function test_charges_mode_requires_at_least_one_charge(): void
    {
        [$plan] = $this->planWithCharges([100_000, 50_000]);

        $this->assertRejected($this->pay($plan, ['payment_mode' => 'charges', 'amount' => 10_000]));

        $this->assertSame(0, $plan->paymentTransactions()->count());
    }

    public function test_payment_mode_is_required(): void
    {
        [$plan] = $this->planWithCharges([100_000]);

        $this->assertRejected($this->pay($plan, ['amount' => 10_000]));

        $this->assertSame(0, $plan->paymentTransactions()->count());
    }

    public function test_charges_mode_rejects_duplicate_charge_ids(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        $this->assertRejected($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 10_000,
            'ledger_ids' => [$rows[0]->id, $rows[0]->id],
        ]));

        $this->assertSame(0, $plan->paymentTransactions()->count());
    }

    public function test_settling_every_charge_marks_the_plan_completed(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 150_000,
            'ledger_ids' => [$rows[0]->id, $rows[1]->id],
        ]));

        $this->assertSame('completed', $plan->fresh()->status);
    }

    public function test_a_payment_stores_its_tax_share_on_each_lined_charge(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);
        $plan->forceFill(['fbr_tax_rate_percent' => 5.0])->save();

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'amount',
            'amount' => 150_000,
        ]));

        $txn = $plan->paymentTransactions()->orderByDesc('id')->first();
        $this->assertSame(150_000.0, (float) $txn->charge_subtotal);
        $this->assertSame(7_500.0, (float) $txn->tax_amount);
        $this->assertSame(157_500.0, (float) $txn->amount_paid);

        // Each allocation stores its own tax share, and the shares sum exactly
        // back to the transaction's aggregate tax.
        $shares = $txn->allocations()->orderBy('id')->get()->map(
            fn ($a) => (float) $a->tax_amount
        );
        $this->assertSame([5_000.0, 2_500.0], $shares->all());
        $this->assertSame(7_500.0, round($shares->sum(), 2));
    }

    public function test_a_payment_without_tax_stores_zero_per_allocation(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000]);
        $plan->forceFill(['fbr_tax_rate_percent' => 0.0])->save();

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'amount',
            'amount' => 100_000,
        ]));

        $txn = $plan->paymentTransactions()->orderByDesc('id')->first();
        $this->assertSame(0.0, (float) $txn->tax_amount);
        $this->assertSame(0.0, (float) $txn->allocations()->sum('tax_amount'));
    }

    public function test_success_message_reports_how_many_charges_were_settled(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000, 25_000]);

        $response = $this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 125_000,
            'ledger_ids' => [$rows[0]->id, $rows[2]->id],
        ]);

        $this->assertPaid($response);
        $this->assertStringContainsString('across 2 selected charges', $this->flash($response, 'success'));
    }

    /**
     * The controller reports every failure as a single 'error' flash rather than
     * the validation error bag, so assert on that key explicitly.
     */
    private function assertPaid($response): void
    {
        $response->assertRedirect();

        $this->assertNull(
            $this->flash($response, 'error'),
            'Expected the payment to succeed but got: '.$this->flash($response, 'error')
        );
        $this->assertNotNull($this->flash($response, 'success'));
    }

    private function assertRejected($response): void
    {
        $response->assertRedirect();

        $this->assertNotNull(
            $this->flash($response, 'error'),
            'Expected the payment to be rejected but it succeeded.'
        );
        $this->assertNull($this->flash($response, 'success'));
    }

    private function flash($response, string $key)
    {
        return $response->baseRequest->session()->get($key);
    }

    /**
     * Build an ownership -> plan -> ledger chain for the given charge amounts.
     *
     * @param  array<int, float>  $amounts
     * @return array{0: PropertyReservedPaymentPlan, 1: Collection<int, PropertyPaymentLedger>}
     */
    private function planWithCharges(array $amounts): array
    {
        $ownerUser = $this->user();

        $owner = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => random_int(1_000_000, 9_999_999),
            'owner_id' => $ownerUser->id,
            'started_at' => now()->toDateString(),
        ]);

        $plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => 1,
            'property_owner_id' => $owner->id,
            'with_installment' => false,
            'final_price' => array_sum($amounts),
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        $rows = collect($amounts)->values()->map(fn ($amount, $i) => PropertyPaymentLedger::create([
            'ownership_payment_plan_id' => $plan->id,
            'charge_type' => 'installment',
            'display_name' => 'Installment #'.($i + 1),
            'sequence_order' => $i + 1,
            'installment_no' => $i + 1,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'remaining_balance' => $amount,
            'due_date' => now()->addMonths($i + 1)->toDateString(),
        ]));

        return [$plan, $rows];
    }

    private function pay(PropertyReservedPaymentPlan $plan, array $data)
    {
        return $this->actingAs($this->user())
            ->from(route('payment.store', $plan))
            ->post(route('payment.store', $plan), $data + [
                'payment_method' => 'cash',
                'reference_no' => 'REF-'.uniqid(),
            ]);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
        ]);

        $user->givePermissionTo('payment_collect');

        return $user;
    }
}
