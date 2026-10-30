<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\FbrTaxRate;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\Society;
use App\Models\User;
use App\Support\PaymentLedgerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPrintViewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FbrTaxRate::query()->delete();
    }

    public function test_the_receipt_page_renders_for_a_real_transaction(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt', $txn))
            ->assertOk()
            ->assertSee('PAYMENT RECEIPT')
            ->assertSee(number_format((float) $txn->charge_subtotal, 2), false)
            ->assertSee(number_format((float) $txn->tax_amount, 2), false)
            ->assertSee(number_format((float) $txn->amount_paid, 2), false);
    }

    public function test_the_receipt_only_lists_chares_this_transaction_settled(): void
    {
        [$plan, $txn] = $this->paidPlan();

        // Two charges exist on the plan; the first payment only touched charge #1.
        $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt', $txn))
            ->assertOk()
            ->assertSee('Installment #1')
            ->assertDontSee('Installment #2');
    }

    public function test_a_returned_payment_receipt_still_lists_its_original_charges(): void
    {
        [$plan, $txn] = $this->paidPlan();

        app(PaymentLedgerService::class)->reversePayment($txn);

        $this->assertSame('returned', $txn->fresh()->status);

        $response = $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt', $txn->fresh()));

        $response->assertOk()
            ->assertSee('Installment #1')
            ->assertSee('50,000.00')
            ->assertDontSee('No charges were allocated to this payment.');
    }

    public function test_a_partly_reversed_payment_receipt_lists_only_the_surviving_charge(): void
    {
        [$plan, $txn] = $this->paidPlan(paymentAmount: 75_000);

        $allocation = $txn->allocations()
            ->whereHas('ledgerRow', fn ($q) => $q->where('display_name', 'Installment #1'))
            ->firstOrFail();

        app(PaymentLedgerService::class)->reverseAllocations($txn, [$allocation->id]);

        $this->assertSame('paid', $txn->fresh()->status);

        $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt', $txn->fresh()))
            ->assertOk()
            ->assertSee('Installment #2')
            ->assertDontSee('Installment #1');
    }

    public function test_the_receipt_auto_prints_only_when_asked(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt', $txn))
            ->assertOk()
            ->assertDontSee('data-receipt-auto-print', false);

        // route()'s third argument is $absolute, NOT query params - extra keys must go
        // in the same array as the route parameter or they are silently dropped.
        $printUrl = route('ownership_payment_plans.payments.receipt', ['transaction' => $txn, 'print' => 1]);

        $this->assertStringContainsString('print=1', $printUrl, 'route() must append print=1');

        $this->actingAs($this->user())
            ->get($printUrl)
            ->assertOk()
            ->assertSee('data-auto-print="1"', false);
    }

    public function test_save_and_print_returns_to_the_page_and_queues_the_receipt_for_the_hidden_printer(): void
    {
        [$plan] = $this->paidPlan();

        $response = $this->actingAs($this->user())
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 1_000,
                'payment_method' => 'cash',
                'reference_no' => 'SP-1',
                'print_receipt' => 1,
            ]);

        $txn = $plan->paymentTransactions()->orderByDesc('id')->first();

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('print_document_url', function (string $url) use ($txn) {
            // The returned page prints through the hidden frame, so the URL
            // must be the plain receipt (no print=1 double print) of this txn.
            return str_contains($url, $txn->uuid) && ! str_contains($url, 'print=1');
        });
    }

    public function test_save_without_print_does_not_redirect_to_a_receipt(): void
    {
        [$plan] = $this->paidPlan();

        $response = $this->actingAs($this->user())
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 1_000,
                'payment_method' => 'cash',
                'reference_no' => 'SP-2',
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('print_document_url');
        $this->assertStringNotContainsString(
            'print=1',
            (string) $response->headers->get('Location'),
            'Save without print must not redirect to an auto-printing receipt.'
        );
    }

    public function test_the_report_page_renders_for_a_date_range(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.report', ['snapshot' => $plan, 'preset' => 'month']))
            ->assertOk()
            ->assertSee('PAYMENTS REPORT')
            ->assertSee('Charge status as at');
    }

    public function test_the_report_excludes_payments_outside_the_range(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $response = $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.report', [
                'snapshot' => $plan,
                'preset' => 'custom',
                'from' => now()->subYears(3)->toDateString(),
                'to' => now()->subYears(2)->toDateString(),
            ]));

        $response->assertOk()
            ->assertSee('No payments were received in this period.');

        // The header must show the requested range, not the default month.
        $response->assertSee(
            now()->subYears(3)->format('M d, Y').' — '.now()->subYears(2)->format('M d, Y'),
            false
        );
    }

    public function test_the_report_shows_a_charge_as_paid_only_when_allocations_cover_it(): void
    {
        [$plan] = $this->paidPlan(paymentAmount: 75_000);

        // 75,000 of 100,000 clears charge #1 and leaves charge #2 partly paid.
        $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.report', ['snapshot' => $plan, 'preset' => 'month']))
            ->assertOk()
            ->assertSee('partial')
            ->assertSee('paid')
            ->assertDontSee('unpaid');
    }

    public function test_the_report_needs_the_print_by_period_permission(): void
    {
        [$plan] = $this->paidPlan();

        // The owner of the plan may read their own payments, but printing the
        // period report is a permission of its own.
        $owner = $plan->propertyOwner->owner;
        $owner->revokePermissionTo('payment_print_by_period');

        $this->actingAs($owner)
            ->get(route('ownership_payment_plans.payments.report', ['snapshot' => $plan, 'preset' => 'month']))
            ->assertForbidden();
    }

    private function paidPlan(float $paymentAmount = 50_000): array
    {
        FbrTaxRate::create([
            'role' => 'buyer',
            'filer_status' => 'non_filer',
            'value_from' => 0,
            'value_to' => null,
            'rate_percent' => 5.00,
        ]);

        $user = $this->user();
        $property = $this->property($user);

        $owner = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => $property->id,
            'owner_id' => $user->id,
            'started_at' => now()->toDateString(),
        ]);

        $plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => $property->id,
            'property_owner_id' => $owner->id,
            'with_installment' => false,
            'final_price' => 100_000,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        foreach ([50_000, 50_000] as $i => $amount) {
            PropertyPaymentLedger::create([
                'ownership_payment_plan_id' => $plan->id,
                'charge_type' => 'installment',
                'display_name' => 'Installment #'.($i + 1),
                'sequence_order' => $i + 1,
                'installment_no' => $i + 1,
                'amount_due' => $amount,
                'amount_paid' => 0,
                'remaining_balance' => $amount,
                'due_date' => now()->addMonth()->toDateString(),
            ]);
        }

        $this->actingAs($user)
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => $paymentAmount,
                'payment_method' => 'cash',
                'reference_no' => 'R-1',
            ]);

        return [$plan->fresh(), $plan->paymentTransactions()->orderByDesc('id')->first()];
    }

    /**
     * A property of its own for the test, so the ownership it creates never
     * collides with the ownership of a real property in the database.
     */
    private function property(User $owner): Property
    {
        $society = Society::create([
            'name' => 'Print Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $owner->id,
        ]);

        $block = Block::create([
            'society_id' => $society->id,
            'name' => 'Block P',
        ]);

        return Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
        ]);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Print',
            'last_name' => 'Tester',
            'email' => 'print.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
            'is_filer' => false,
        ]);

        $user->givePermissionTo(['payment_collect', 'payment_print_by_period']);

        return $user;
    }
}
