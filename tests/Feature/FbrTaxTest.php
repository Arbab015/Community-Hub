<?php

namespace Tests\Feature;

use App\Models\FbrTaxRate;
use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\User;
use App\Support\FbrTaxCalculator;
use App\Support\FbrTaxRateResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FbrTaxTest extends TestCase
{
    use DatabaseTransactions;

    private FbrTaxRateResolver $resolver;

    private FbrTaxCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new FbrTaxRateResolver;
        $this->calculator = new FbrTaxCalculator;

        FbrTaxRate::query()->delete();
    }

    /* ------------------------------------------------------------------ */
    /* Slab resolution */
    /* ------------------------------------------------------------------ */

    public function test_it_picks_the_slab_whose_range_contains_the_price(): void
    {
        $this->rate('buyer', 'non_filer', 0, 50_000_000, 1.25);
        $this->rate('buyer', 'non_filer', 50_000_000, 100_000_000, 14.50);

        $this->assertSame(1.25, $this->resolver->ratePercent(49_999_999));
        $this->assertSame(14.50, $this->resolver->ratePercent(50_000_001));
    }

    public function test_value_to_is_exclusive_so_a_price_on_the_boundary_takes_the_later_slab(): void
    {
        $this->rate('buyer', 'non_filer', 0, 50_000_000, 1.25);
        $this->rate('buyer', 'non_filer', 50_000_000, 100_000_000, 14.50);

        // Exactly on value_from of the second slab -> that slab.
        $this->assertSame(14.50, $this->resolver->ratePercent(50_000_000));
        // One below -> the first.
        $this->assertSame(1.25, $this->resolver->ratePercent(49_999_999));
    }

    public function test_a_null_value_to_means_and_above(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 18.50);

        $this->assertSame(18.50, $this->resolver->ratePercent(100));
        $this->assertSame(18.50, $this->resolver->ratePercent(999_999_999_999));
    }

    public function test_filer_and_non_filer_rows_are_selected_independently(): void
    {
        $this->rate('buyer', 'filer', 0, null, 1.25);
        $this->rate('buyer', 'non_filer', 0, null, 10.50);

        $this->assertSame(1.25, $this->resolver->ratePercent(1_000_000, FbrTaxRateResolver::FILER));
        $this->assertSame(10.50, $this->resolver->ratePercent(1_000_000, FbrTaxRateResolver::NON_FILER));
    }

    public function test_seller_slabs_are_never_used_for_a_buyer_lookup(): void
    {
        $this->rate('seller', 'non_filer', 0, null, 99.00);
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        $this->assertSame(5.00, $this->resolver->ratePercent(1_000_000, FbrTaxRateResolver::NON_FILER, FbrTaxRateResolver::ROLE_BUYER));
    }

    public function test_an_uncovered_price_falls_back_to_the_base_slab_instead_of_returning_null(): void
    {
        // Deliberately no value_from = 0 row: a real gap in the configuration.
        $this->rate('buyer', 'non_filer', 50_000_000, 100_000_000, 14.50);
        $this->rate('buyer', 'non_filer', 0, null, 2.00);

        $this->assertSame(2.00, $this->resolver->ratePercent(1_000));
    }

    public function test_no_configured_rate_yields_zero_rather_than_an_error(): void
    {
        $this->assertSame(0.0, $this->resolver->ratePercent(1_000_000));
    }

    /* ------------------------------------------------------------------ */
    /* Split independence - the headline guarantee */
    /* ------------------------------------------------------------------ */

    public function test_tax_is_identical_whether_paid_in_one_transaction_or_many(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        // One shot.
        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($plan, 100_000);
        $oneShot = (float) $plan->paymentTransactions()->sum('tax_amount');

        // Many, summing to exactly the same charge total.
        [$split] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($split, 33_333.33);
        $this->pay($split, 33_333.33);
        $this->pay($split, 33_333.34);
        $many = (float) $split->paymentTransactions()->sum('tax_amount');

        $this->assertSame(5_000.00, $oneShot);
        $this->assertSame($oneShot, $many, 'Tax must not depend on how many transactions were used.');
    }

    public function test_split_independence_holds_for_every_configured_rate(): void
    {
        // 1.25% and 18.50% are the rates that actually drift under rounding.
        foreach ([1.25, 10.50, 18.50, 5.00] as $rate) {
            $this->setUp();
            $this->rate('buyer', 'non_filer', 0, null, $rate);

            [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
            $this->pay($plan, 17_777.77);
            $this->pay($plan, 17_777.77);
            $this->pay($plan, 17_777.77);
            $this->pay($plan, 17_777.77);
            $this->pay($plan, 13_333.32);
            $this->pay($plan, 15_555.60);

            $expected = round(100_000 * $rate / 100, 2);
            $actual = (float) $plan->paymentTransactions()->sum('tax_amount');

            $this->assertSame($expected, $actual, "Rate {$rate}% did not reconcile across six transactions.");
        }
    }

    public function test_two_hundred_transactions_never_overshoot_the_plan_target(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 10.50);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        for ($i = 0; $i < 200; $i++) {
            $this->pay($plan, 500);
        }

        $tax = (float) $plan->paymentTransactions()->sum('tax_amount');

        $this->assertSame(10_500.00, $tax);
        $this->assertSame(100_000.00, (float) $plan->paymentTransactions()->sum('charge_subtotal'));
        $this->assertSame(110_500.00, (float) $plan->paymentTransactions()->sum('amount_paid'));
    }

    public function test_the_final_transaction_absorbs_the_rounding_residual(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($plan, 33_333.33);
        $this->pay($plan, 33_333.33);
        $this->pay($plan, 33_333.34);

        $last = $plan->paymentTransactions()->orderByDesc('id')->first();

        // Naive proportional tax would be 1666.67; the cap brings it to 1666.66.
        $this->assertSame(1_666.66, (float) $last->tax_amount);
        $this->assertTrue((bool) $last->tax_capped);
    }

    public function test_tax_never_goes_negative_once_the_target_is_exhausted(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        $first = $this->calculator->taxForTransaction(100.0, 5.0, 5_000.00, 100_000.0);

        $this->assertSame(0.0, $first['tax']);
        $this->assertSame(0.0, $first['headroom_before']);
    }

    public function test_a_zero_rate_plan_collects_no_tax(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 0.00);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($plan, 25_000);

        $txn = $plan->paymentTransactions()->first();
        $this->assertSame(0.0, (float) $txn->tax_amount);
        $this->assertSame(25_000.0, (float) $txn->amount_paid);
    }

    /* ------------------------------------------------------------------ */
    /* Stored values */
    /* ------------------------------------------------------------------ */

    public function test_amount_paid_equals_charge_subtotal_plus_tax(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($plan, 40_000);

        $txn = $plan->paymentTransactions()->first();

        $this->assertSame(40_000.0, (float) $txn->charge_subtotal);
        $this->assertSame(2_000.0, (float) $txn->tax_amount);
        $this->assertSame(42_000.0, (float) $txn->amount_paid);
        $this->assertSame(5.0, (float) $txn->tax_rate_percent);
    }

    public function test_allocations_always_reconcile_to_the_charge_subtotal_and_not_the_tax(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 10.50);

        [$plan] = $this->planWithCharges([30_000, 30_000, 40_000], 100_000, isFiler: false);
        $this->pay($plan, 55_000);

        $txn = $plan->paymentTransactions()->first();

        $this->assertSame(55_000.0, (float) $txn->allocations()->sum('amount_allocated'));
        $this->assertSame((float) $txn->charge_subtotal, (float) $txn->allocations()->sum('amount_allocated'));
        $this->assertGreaterThan((float) $txn->allocations()->sum('amount_allocated'), (float) $txn->amount_paid === 0 ? 0 : (float) $txn->amount_paid);
    }

    public function test_a_filer_buyer_is_charged_the_filer_rate(): void
    {
        $this->rate('buyer', 'filer', 0, null, 1.25);
        $this->rate('buyer', 'non_filer', 0, null, 10.50);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: true);
        $this->pay($plan, 100_000);

        $this->assertSame(1_250.0, (float) $plan->paymentTransactions()->sum('tax_amount'));
    }

    public function test_the_rate_is_snapshotted_so_a_later_slab_edit_cannot_reprice_past_payments(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($plan, 40_000);
        $firstTax = (float) $plan->paymentTransactions()->first()->tax_amount;

        // The admin rewrites the slab to 20% AFTER the payment was recorded.
        FbrTaxRate::query()->update(['rate_percent' => 20.00]);

        $this->pay($plan->fresh(), 40_000);
        $secondTax = (float) $plan->paymentTransactions()->orderByDesc('id')->first()->tax_amount;

        $this->assertSame(2_000.0, $firstTax);
        $this->assertSame(2_000.0, $secondTax, 'Snapshotted rate must survive a slab edit.');
    }

    public function test_reversing_a_payment_releases_its_tax_headroom(): void
    {
        $this->rate('buyer', 'non_filer', 0, null, 5.00);

        [$plan] = $this->planWithCharges([100_000], 100_000, isFiler: false);
        $this->pay($plan, 40_000);
        $this->pay($plan, 60_000);

        $this->assertSame(5_000.0, (float) $plan->paymentTransactions()->where('status', 'paid')->sum('tax_amount'));

        $first = $plan->paymentTransactions()->orderBy('id')->first();
        app(\App\Support\PaymentLedgerService::class)->reversePayment($first);

        // The reversed transaction is kept as history - never deleted - and only
        // the surviving one still counts towards the collected tax.
        $this->assertNotNull($first->fresh());
        $this->assertSame('returned', $first->fresh()->status);
        $this->assertSame(3_000.0, (float) $plan->paymentTransactions()->where('status', 'paid')->sum('tax_amount'));
    }

    /* ------------------------------------------------------------------ */
    /* Proration for the receipt */
    /* ------------------------------------------------------------------ */

    public function test_prorated_tax_shares_always_sum_back_to_the_transaction_tax(): void
    {
        $shares = $this->calculator->prorate(1_904.76, [25_000, 15_000]);

        $this->assertCount(2, $shares);
        $this->assertSame(1_904.76, round(array_sum($shares), 2));
    }

    public function test_proration_over_many_lines_stays_exact(): void
    {
        $amounts = [3_333.33, 3_333.33, 3_333.34, 1.00, 7.77];
        $shares = $this->calculator->prorate(1_234.57, $amounts);

        $this->assertSame(1_234.57, round(array_sum($shares), 2));
    }

    public function test_prorating_a_zero_tax_yields_zero_everywhere(): void
    {
        $this->assertSame([0.0, 0.0, 0.0], $this->calculator->prorate(0.0, [10, 20, 30]));
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    private function rate(string $role, string $status, ?int $from, ?int $to, float $percent): FbrTaxRate
    {
        return FbrTaxRate::create([
            'role' => $role,
            'filer_status' => $status,
            'value_from' => $from ?? 0,
            'value_to' => $to,
            'rate_percent' => $percent,
        ]);
    }

    /**
     * @param  array<int, float>  $charges
     * @return array{0: PropertyReservedPaymentPlan, 1: \Illuminate\Support\Collection}
     */
    private function planWithCharges(array $charges, float $finalPrice, bool $isFiler): array
    {
        $user = $this->user($isFiler);

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
            'final_price' => $finalPrice,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        $rows = collect($charges)->values()->map(fn ($amount, $i) => PropertyPaymentLedger::create([
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

    private function pay(PropertyReservedPaymentPlan $plan, float $amount): void
    {
        $this->actingAs($this->user(false))
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => $amount,
                'payment_method' => 'cash',
                'reference_no' => 'R-'.uniqid(),
            ])
            ->assertSessionHasNoErrors();
    }

    private function user(bool $isFiler = false): User
    {
        $user = User::create([
            'first_name' => 'Tax',
            'last_name' => 'Test',
            'email' => 'tax.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
            'is_filer' => $isFiler,
        ]);

        $user->givePermissionTo('payment_collect');

        return $user;
    }
}
