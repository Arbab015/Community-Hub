<?php

namespace Tests\Feature;

use App\Models\FbrTaxRate;
use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\User;
use App\Support\PaymentLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un-paying charges chosen in the ledger table.
 *
 * The behaviour these lock down is the reason the selection exists at all: a
 * bulk payment settles several charges at once, and un-paying one of them must
 * not destroy the payment or reopen the charges paid alongside it.
 */
class PaymentUnpayChargesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Other suites share this database; clear the slab table so the rate
        // helper here is authoritative instead of competing with seeded rows.
        FbrTaxRate::query()->delete();
    }

    public function test_unpaying_one_charge_of_a_bulk_payment_keeps_the_other_charges_settled(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        // One payment settles both charges - the bulk case.
        $txn = $this->pay($plan, 150_000);
        $this->assertSame(2, $txn->allocations()->count());

        $this->unpay($plan, [$rows[0]->uuid])->assertOk();

        $first = $rows[0]->fresh();
        $this->assertSame(0.0, (float) $first->amount_paid);
        $this->assertSame(100_000.0, (float) $first->remaining_balance);
        $this->assertNotSame('paid', $first->payment_status);

        $second = $rows[1]->fresh();
        $this->assertSame(50_000.0, (float) $second->amount_paid);
        $this->assertSame(0.0, (float) $second->remaining_balance);
        $this->assertSame('paid', $second->payment_status, 'The sibling charge must stay settled.');

        // The payment survives because one charge is still covered by it.
        $txn->refresh();
        $this->assertSame(1, $txn->allocations()->count());
        $this->assertSame(50_000.0, (float) $txn->charge_subtotal);
    }

    public function test_a_partial_reversal_reprices_the_payment_and_releases_tax_proportionally(): void
    {
        $this->rate(5.00);

        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);
        $txn = $this->pay($plan, 150_000);

        // 150,000 at 5% is a 7,500 tax, collected in full as this settles the plan.
        $this->assertSame(7_500.0, (float) $txn->tax_amount);
        $this->assertSame(157_500.0, (float) $txn->amount_paid);

        $this->unpay($plan, [$rows[0]->uuid])->assertOk();

        $txn->refresh();

        // 100,000 of 150,000 released => 5,000 of the 7,500 tax returned.
        $this->assertSame(50_000.0, (float) $txn->charge_subtotal);
        $this->assertSame(2_500.0, (float) $txn->tax_amount);
        $this->assertSame(52_500.0, (float) $txn->amount_paid);

        // The invariant the ledger relies on must survive the re-pricing.
        $this->assertSame(
            round((float) $txn->charge_subtotal + (float) $txn->tax_amount, 2),
            round((float) $txn->amount_paid, 2)
        );

        $summary = app(PaymentLedgerService::class)->summary($plan->fresh());
        $this->assertSame(2_500.0, (float) $summary['tax_collected']);
    }

    public function test_unpaying_refunds_the_stored_tax_share_and_reports_it(): void
    {
        $this->rate(5.00);

        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);
        $txn = $this->pay($plan, 150_000);

        $shares = $txn->allocations()->orderBy('id')->get();
        $this->assertSame([5_000.0, 2_500.0], $shares->map(fn ($a) => (float) $a->tax_amount)->all());
        $this->assertSame(7_500.0, round((float) $shares->sum('tax_amount'), 2));

        $response = $this->unpay($plan, [$rows[0]->uuid]);
        $response->assertOk();
        $response->assertJsonPath('message', fn ($m) => str_contains($m, 'FBR tax 5,000.00 PKR refunded with it.'));

        $txn->refresh();
        $this->assertSame(50_000.0, (float) $txn->charge_subtotal);
        $this->assertSame(2_500.0, (float) $txn->tax_amount);
        $this->assertSame(52_500.0, (float) $txn->amount_paid);

        // The surviving allocation keeps exactly its own stored share.
        $this->assertSame(2_500.0, (float) $txn->allocations()->sum('tax_amount'));
    }

    public function test_unpaying_every_charge_of_a_payment_returns_that_payment(): void
    {
        [$plan, $rows] = $this->planWithCharges([50_000, 50_000, 25_000]);

        // 75,000 cascades oldest-first: fully covers charge 1 and half of charge 2.
        $txn = $this->pay($plan, 75_000);
        $this->assertSame(2, $txn->allocations()->count());
        $this->assertSame(0.0, (float) $rows[2]->fresh()->amount_paid);

        $this->unpay($plan, [$rows[0]->uuid, $rows[1]->uuid])->assertOk();

        $this->assertNotNull($txn->fresh());
        $this->assertSame('returned', $txn->fresh()->status);

        // The third charge was never part of that payment and is untouched.
        $this->assertSame(0.0, (float) $rows[2]->fresh()->amount_paid);
    }

    public function test_a_selection_spanning_two_payments_is_reversed_in_one_call(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000, 25_000]);

        $first = $this->pay($plan, 100_000);   // covers charge 1
        $second = $this->pay($plan, 75_000);   // covers charges 2 and 3

        // One charge from each payment.
        $this->unpay($plan, [$rows[0]->uuid, $rows[2]->uuid])->assertOk();

        $this->assertNotNull($first->fresh());
        $this->assertSame('returned', $first->fresh()->status);
        $this->assertNotNull($second->fresh());
        $this->assertSame('paid', $second->fresh()->status);
        $this->assertSame(50_000.0, (float) $second->fresh()->charge_subtotal);

        $this->assertSame(0.0, (float) $rows[0]->fresh()->amount_paid);
        $this->assertSame(50_000.0, (float) $rows[1]->fresh()->amount_paid);
        $this->assertSame(0.0, (float) $rows[2]->fresh()->amount_paid);
    }

    public function test_unpaying_reopens_the_charge_for_payment_again(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        $this->pay($plan, 150_000);
        $this->unpay($plan, [$rows[0]->uuid])->assertOk();

        // The reopened charge must be payable again, onto a fresh transaction.
        $repay = $this->pay($plan, 100_000);

        $this->assertSame(100_000.0, (float) $rows[0]->fresh()->amount_paid);
        $this->assertSame(0.0, (float) $rows[0]->fresh()->remaining_balance);
        $this->assertSame(100_000.0, (float) $repay->charge_subtotal);
    }

    public function test_a_reopened_plan_returns_to_active(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000]);

        $this->pay($plan, 100_000);
        $this->assertSame('completed', $plan->fresh()->status);

        $this->unpay($plan, [$rows[0]->uuid])->assertOk();

        $this->assertSame('active', $plan->fresh()->status);
    }

    public function test_a_fully_un_paid_plan_can_be_edited_again(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        $this->pay($plan, 150_000);
        $this->assertSame('completed', $plan->fresh()->status);

        $this->unpay($plan, [$rows[0]->uuid, $rows[1]->uuid])->assertOk();

        $editor = $this->user();
        $editor->givePermissionTo('payment_plan_edit');

        $response = $this->actingAs($editor)
            ->put(route('ownership_payment_plans.update', $plan), [
                'listed_price' => 150_000,
                'price_unit' => 'PKR',
                'booking_fee' => 50_000,
                'booking_fee_in_per' => 0,
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $this->assertNotSame('completed', $plan->fresh()->status);
    }

    public function test_charges_belonging_to_another_plan_are_rejected(): void
    {
        [$plan] = $this->planWithCharges([100_000]);
        [$other, $otherRows] = $this->planWithCharges([50_000]);

        $this->pay($plan, 100_000);
        $this->pay($other, 50_000);

        $response = $this->unpay($plan, [$otherRows[0]->uuid]);

        $response->assertStatus(422);
        $this->assertSame(50_000.0, (float) $otherRows[0]->fresh()->amount_paid, 'The other plan must be untouched.');
    }

    public function test_at_least_one_charge_must_be_selected(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000]);
        $this->pay($plan, 100_000);

        $this->unpay($plan, [])->assertStatus(422);

        $this->assertSame(100_000.0, (float) $rows[0]->fresh()->amount_paid);
    }

    public function test_reversing_a_charge_with_no_payment_is_rejected(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000, 50_000]);

        // Only the first charge is paid.
        $this->pay($plan, 100_000);

        $this->unpay($plan, [$rows[1]->uuid])->assertStatus(422);
    }

    public function test_reversing_charges_requires_the_payment_collect_permission(): void
    {
        [$plan, $rows] = $this->planWithCharges([100_000]);
        $this->pay($plan, 100_000);

        $outsider = User::create([
            'first_name' => 'No',
            'last_name' => 'Permission',
            'email' => 'no.perm.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
        ]);

        $this->actingAs($outsider)
            ->postJson(route('ownership_payment_plans.payments.reverse_charges', $plan), [
                'ledger_uuids' => [$rows[0]->uuid],
            ])
            ->assertForbidden();

        $this->assertSame(100_000.0, (float) $rows[0]->fresh()->amount_paid);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    private function unpay(PropertyReservedPaymentPlan $plan, array $ledgerUuids)
    {
        return $this->actingAs($this->user())->postJson(
            route('ownership_payment_plans.payments.reverse_charges', $plan),
            ['ledger_uuids' => $ledgerUuids]
        );
    }

    private function pay(PropertyReservedPaymentPlan $plan, float $amount)
    {
        $this->actingAs($this->user())
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => $amount,
                'payment_method' => 'cash',
                'reference_no' => 'R-'.uniqid(),
            ])
            ->assertSessionHasNoErrors();

        return $plan->paymentTransactions()->orderByDesc('id')->first();
    }

    /**
     * @param  array<int, float>  $amounts
     * @return array{0: PropertyReservedPaymentPlan, 1: Collection<int, PropertyPaymentLedger>}
     */
    private function planWithCharges(array $amounts): array
    {
        $user = $this->user();

        $owner = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => random_int(1_000_000, 9_999_999),
            'owner_id' => $user->id,
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

    private function rate(float $percent): FbrTaxRate
    {
        return FbrTaxRate::create([
            'role' => 'buyer',
            'filer_status' => 'non_filer',
            'value_from' => 0,
            'value_to' => null,
            'rate_percent' => $percent,
        ]);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Unpay',
            'last_name' => 'Test',
            'email' => 'unpay.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
            'is_filer' => false,
        ]);

        $user->givePermissionTo('payment_collect');

        return $user;
    }
}
