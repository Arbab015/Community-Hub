<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\PaymentLateFee;
use App\Models\PaymentLateFeeRule;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\Society;
use App\Models\SocietySetting;
use App\Models\User;
use App\Support\LateFeeService;
use App\Support\PaymentLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LateFeeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_percentage_late_fee_is_added_to_an_overdue_charge(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 20);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 5);
        $this->rule($society, graceDays: 30, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 25);

        $response = $this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 100_000,
            'ledger_ids' => [$row->id],
        ]);

        $this->assertPaid($response);

        $transaction = $plan->paymentTransactions()->latest('id')->firstOrFail();
        $fee = $transaction->lateFees()->firstOrFail();

        $this->assertSame(5_000.0, (float) $fee->fee_amount);
        $this->assertSame(20, (int) $fee->days_late);
        $this->assertSame(15, (int) $fee->rule_grace_days);
        $this->assertSame('collected', $fee->status);

        // The fee is charged on top of the charge and never carries FBR tax, so
        // it simply adds to the transaction total.
        $this->assertSame(100_000.0, (float) $transaction->charge_subtotal);
        $this->assertSame(5_000.0, (float) $transaction->late_fee_amount);
        $this->assertSame(
            round(100_000 + (float) $transaction->tax_amount + 5_000, 2),
            (float) $transaction->amount_paid
        );
    }

    public function test_the_receipt_shows_late_fee_in_its_own_column_within_the_line_total(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 20);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 5);

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 100_000,
            'ledger_ids' => [$row->id],
        ]));

        $transaction = $plan->paymentTransactions()->latest('id')->firstOrFail();

        $response = $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt', $transaction));

        $response->assertOk()
            ->assertSee('LATE FEE')
            ->assertSee('5,000.00')
            ->assertSee(number_format(100_000 + (float) $transaction->tax_amount + 5_000, 2))
            ->assertDontSee('days late');

        $html = (string) $response->getContent();
        $taxColumn = strpos($html, 'FBR TAX');
        $lateFeeColumn = strpos($html, 'LATE FEE');
        $totalColumn = strpos($html, 'TOTAL AMOUNT');

        $this->assertTrue(
            $taxColumn !== false && $lateFeeColumn !== false && $totalColumn !== false
            && $taxColumn < $lateFeeColumn && $lateFeeColumn < $totalColumn,
            'The late fee column must sit between FBR tax and the line total.'
        );
    }

    public function test_fixed_late_fee_is_only_charged_when_the_charge_is_cleared(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 20);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_FIXED, amount: 1_500);

        // A partial payment does not clear the charge, so no fixed fee yet.
        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 40_000,
            'ledger_ids' => [$row->id],
        ]));

        $this->assertSame(0, PaymentLateFee::where('property_payment_ledger_id', $row->id)->count());

        // The settling payment carries the fixed fee once.
        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 60_000,
            'ledger_ids' => [$row->id],
        ], paidAt: now()->addDay()->toDateString()));

        $transaction = $plan->paymentTransactions()->latest('id')->firstOrFail();

        $this->assertSame(1, PaymentLateFee::where('property_payment_ledger_id', $row->id)->count());
        $this->assertSame(1_500.0, (float) $transaction->late_fee_amount);
        $this->assertSame(
            round(60_000 + (float) $transaction->tax_amount + 1_500, 2),
            (float) $transaction->amount_paid
        );
    }

    public function test_the_preview_returns_the_same_late_fee_as_the_recorded_payment(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 20);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 5);
        $this->rule($society, graceDays: 30, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 25);

        $preview = $this->actingAs($this->user())
            ->getJson(route('payments.late_fee_preview', $plan).'?'.http_build_query([
                'payment_mode' => 'charges',
                'amount' => 100_000,
                'paid_at' => now()->toDateString(),
                'ledger_ids' => [$row->id],
            ]))
            ->assertOk()
            ->json();

        $this->assertTrue($preview['ok']);
        $this->assertSame(5_000.0, (float) $preview['late_fee_amount']);
        $this->assertSame(1, count($preview['late_fees']));
        $this->assertSame($row->id, $preview['late_fees'][0]['ledger_id']);
    }

    public function test_no_late_fee_is_charged_when_the_society_has_it_disabled(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 20);

        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 5);

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 100_000,
            'ledger_ids' => [$row->id],
        ]));

        $transaction = $plan->paymentTransactions()->latest('id')->firstOrFail();

        $this->assertSame(0.0, (float) $transaction->late_fee_amount);
        $this->assertSame(0, PaymentLateFee::where('property_payment_ledger_id', $row->id)->count());
    }

    public function test_a_collected_late_fee_survives_reversing_the_charge(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 20);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 5);

        $this->assertPaid($this->pay($plan, [
            'payment_mode' => 'charges',
            'amount' => 100_000,
            'ledger_ids' => [$row->id],
        ]));

        $transaction = $plan->paymentTransactions()->latest('id')->firstOrFail();

        app(PaymentLedgerService::class)->reverseCharges([$row->fresh()]);

        $this->assertSame(1, PaymentLateFee::where('property_payment_ledger_id', $row->id)->count());
        $this->assertSame(5_000.0, (float) $transaction->fresh()->late_fee_amount);
        $this->assertSame(5_000.0, (float) $transaction->fresh()->amount_paid);
    }

    public function test_the_minimum_grace_period_is_free_and_later_tiers_are_thresholds(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 15);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 1);
        $this->rule($society, graceDays: 30, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 2);
        $this->rule($society, graceDays: 43, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 3);

        $cases = [
            15 => [0.0, null],
            16 => [1_000.0, 15],
            30 => [1_000.0, 15],
            31 => [2_000.0, 30],
            43 => [2_000.0, 30],
            44 => [3_000.0, 43],
        ];

        foreach ($cases as $daysLate => [$fee, $ruleGrace]) {
            $row->update(['due_date' => now()->subDays($daysLate)->toDateString()]);

            $result = app(LateFeeService::class)->calculate($plan, [[
                'ledger' => $row->fresh(),
                'amount' => 100_000,
                'settles_row' => true,
            ]], now());

            $this->assertSame($fee, $result['total'], "Wrong fee for {$daysLate} days late.");
            $this->assertSame(
                $ruleGrace,
                $result['charges'][0]['rule_grace_days'] ?? null,
                "Wrong rule selected for {$daysLate} days late."
            );
        }
    }

    public function test_a_null_grace_rule_is_the_catch_all_after_the_last_tier(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 31);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 1);
        $this->rule($society, graceDays: 30, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 2);
        $this->rule($society, graceDays: null, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 4);

        $result = app(LateFeeService::class)->calculate($plan, [[
            'ledger' => $row->fresh(),
            'amount' => 100_000,
            'settles_row' => true,
        ]], now());

        $this->assertSame(4_000.0, $result['total']);
        $this->assertNull($result['charges'][0]['rule_grace_days']);
    }

    public function test_an_overdue_charge_becomes_late_only_after_the_minimum_grace_period(): void
    {
        [$society, $plan, $row] = $this->planWithCharge(100_000, daysLate: 15);

        $this->enableLateFees($society);
        $this->rule($society, graceDays: 15, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 5);
        $this->rule($society, graceDays: 30, type: PaymentLateFeeRule::TYPE_PERCENTAGE, amount: 25);

        $summary = app(PaymentLedgerService::class)->summary($plan);
        $this->assertSame(1, $summary['by_status']['current_due']);
        $this->assertSame(0, $summary['by_status']['late']);

        $row->update(['due_date' => now()->subDays(16)->toDateString()]);

        $summary = app(PaymentLedgerService::class)->summary($plan);
        $this->assertSame(0, $summary['by_status']['current_due']);
        $this->assertSame(1, $summary['by_status']['late']);
    }

    /**
     * @return array{0: Society, 1: PropertyReservedPaymentPlan, 2: PropertyPaymentLedger}
     */
    private function planWithCharge(float $amount, int $daysLate): array
    {
        $user = $this->user();

        $society = Society::create([
            'name' => 'Late Fee Society '.uniqid(),
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $user->id,
        ]);

        $block = Block::create([
            'society_id' => $society->id,
            'name' => 'Block L',
        ]);

        $property = Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
            'owner_id' => $user->id,
        ]);

        $owner = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $property->id,
            'owner_id' => $user->id,
            'started_at' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $property->id,
            'property_owner_id' => $owner->id,
            'with_installment' => false,
            'final_price' => $amount,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        $row = PropertyPaymentLedger::create([
            'ownership_payment_plan_id' => $plan->id,
            'charge_type' => 'installment',
            'display_name' => 'Installment #1',
            'sequence_order' => 1,
            'installment_no' => 1,
            'amount_due' => $amount,
            'amount_paid' => 0,
            'remaining_balance' => $amount,
            'due_date' => now()->subDays($daysLate)->toDateString(),
        ]);

        return [$society, $plan, $row];
    }

    private function enableLateFees(Society $society): void
    {
        SocietySetting::set($society->id, SocietySetting::ENABLE_LATE_FEE_RULE, true);
    }

    private function rule(Society $society, ?int $graceDays, string $type, float $amount): PaymentLateFeeRule
    {
        return PaymentLateFeeRule::create([
            'society_id' => $society->id,
            'grace_period_days' => $graceDays,
            'fine_type' => $type,
            'fine_amount' => $amount,
        ]);
    }

    private function pay(PropertyReservedPaymentPlan $plan, array $data, ?string $paidAt = null)
    {
        return $this->actingAs($this->user())
            ->from(route('payment.store', $plan))
            ->post(route('payment.store', $plan), $data + [
                'payment_method' => 'cash',
                'reference_no' => 'REF-'.uniqid(),
                'paid_at' => $paidAt ?? now()->toDateString(),
            ]);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Late',
            'last_name' => 'Fee',
            'email' => 'late.'.uniqid().'@example.com',
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

    private function assertPaid($response): void
    {
        $response->assertRedirect();

        $this->assertNull(
            $this->flash($response, 'error'),
            'Expected the payment to succeed but got: '.$this->flash($response, 'error')
        );
        $this->assertNotNull($this->flash($response, 'success'));
    }

    private function flash($response, string $key)
    {
        return $response->baseRequest->session()->get($key);
    }
}
