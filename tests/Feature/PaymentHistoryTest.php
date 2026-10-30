<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\FbrTaxRate;
use App\Models\PaymentTransaction;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyPaymentLedger;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FbrTaxRate::query()->delete();
    }

    public function test_history_lists_every_transaction_for_the_plan(): void
    {
        [$plan] = $this->paidPlan();

        $this->actingAs($this->user())
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 10_000,
                'payment_method' => 'bank_transfer',
                'reference_no' => 'HIST-2',
            ]);

        $response = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', $plan))
            ->assertOk();

        $data = $response->json();

        $this->assertTrue($data['ok']);
        $this->assertCount(2, $data['transactions']);
        $this->assertSame(2, $data['summary']['count']);

        // Newest first.
        $this->assertSame('HIST-2', $data['transactions'][0]['reference_no']);
    }

    public function test_history_can_be_filtered_by_reference_and_method(): void
    {
        [$plan] = $this->paidPlan();

        $this->actingAs($this->user())
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 10_000,
                'payment_method' => 'bank_transfer',
                'reference_no' => 'NEEDLE-9',
            ]);

        $byRef = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $plan, 'q' => 'NEEDLE']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $byRef['transactions']);
        $this->assertSame('NEEDLE-9', $byRef['transactions'][0]['reference_no']);

        $byMethod = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $plan, 'method' => 'bank_transfer']))
            ->assertOk()
            ->json();

        $this->assertCount(1, $byMethod['transactions']);
        $this->assertSame('bank_transfer', $byMethod['transactions'][0]['method']);

        // A filter matching nothing returns an empty page, not an error.
        $none = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $plan, 'q' => 'zzz-nope']))
            ->assertOk()
            ->json();

        $this->assertSame([], $none['transactions']);
        $this->assertSame(0, $none['summary']['count']);
    }

    public function test_history_totals_cover_the_whole_filtered_set_not_just_the_page(): void
    {
        [$plan] = $this->paidPlan();

        $this->actingAs($this->user())
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 10_000,
                'payment_method' => 'cash',
            ]);

        $data = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $plan, 'per_page' => 5]))
            ->assertOk()
            ->json();

        $this->assertSame(2, $data['summary']['count']);

        // 50,000 + 10,000 of charges, plus the 5% FBR tax on top of each.
        $this->assertEqualsWithDelta(60_000, $data['summary']['charges'], 0.01);
        $this->assertEqualsWithDelta(3_000, $data['summary']['tax'], 0.01);
        $this->assertEqualsWithDelta(63_000, $data['summary']['total'], 0.01);
    }

    public function test_each_history_row_exposes_print_and_download_receipt_urls(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $row = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', $plan))
            ->assertOk()
            ->json('transactions.0');

        $this->assertSame(
            route('ownership_payment_plans.payments.receipt', $txn),
            $row['receipt_url']
        );
        $this->assertSame(
            route('ownership_payment_plans.payments.receipt.download', $txn),
            $row['download_url']
        );
        $this->assertStringContainsString('print=1', $row['receipt_print_url']);
    }

    public function test_history_scope_all_includes_payments_from_a_previous_owner(): void
    {
        // Both plans sit on the SAME property: a previous owner who has since
        // released it, and the current one.
        $property = $this->property();

        [$previousPlan] = $this->planWithPayment(
            property: $property,
            reference: 'PREV-1',
            ownerName: 'Previous',
            ended: true,
        );
        [$currentPlan] = $this->planWithPayment(
            property: $property,
            reference: 'CURR-1',
            ownerName: 'Current',
        );

        $current = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', $currentPlan))
            ->assertOk()
            ->json();

        $this->assertSame('current', $current['scope']);
        $this->assertCount(1, $current['transactions']);
        $this->assertSame('CURR-1', $current['transactions'][0]['reference_no']);

        $all = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $currentPlan, 'scope' => 'all']))
            ->assertOk()
            ->json();

        $this->assertSame('all', $all['scope']);
        $this->assertCount(2, $all['transactions']);

        // Every row identifies the owner and period it was received under.
        $byReference = collect($all['transactions'])->keyBy('reference_no');

        $this->assertSame('Current Buyer', $byReference['CURR-1']['owner_name']);
        $this->assertTrue($byReference['CURR-1']['is_current_plan']);

        $this->assertSame('Previous Buyer', $byReference['PREV-1']['owner_name']);
        $this->assertFalse($byReference['PREV-1']['is_current_plan']);

        // A released ownership shows its end date; the live one reads ongoing.
        $this->assertStringContainsString('→', (string) $byReference['PREV-1']['ownership_period']);
        $this->assertStringNotContainsString('ongoing', (string) $byReference['PREV-1']['ownership_period']);
        $this->assertStringContainsString('ongoing', (string) $byReference['CURR-1']['ownership_period']);
    }

    public function test_history_scope_all_excludes_transactions_from_another_property(): void
    {
        $currentPlan = $this->planWithPayment(
            property: $this->property(),
            reference: 'CURR-1',
            ownerName: 'Current',
        )[0];

        // A different property, same database - must never leak into "all".
        $this->planWithPayment(
            property: $this->property(),
            reference: 'OTHER-1',
            ownerName: 'Somebody',
        );

        $all = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $currentPlan, 'scope' => 'all']))
            ->assertOk()
            ->json();

        $this->assertSame(['CURR-1'], collect($all['transactions'])->pluck('reference_no')->all());
    }

    public function test_history_requires_the_payment_collect_permission(): void
    {
        [$plan] = $this->paidPlan();

        $user = $this->user();
        $user->revokePermissionTo('payment_collect');

        $this->actingAs($user)
            ->getJson(route('ownership_payment_plans.payments.history', $plan))
            ->assertForbidden();
    }

    public function test_the_history_returns_the_pagination_of_the_app(): void
    {
        [$plan] = $this->paidPlan();

        // More transactions than fit on one page, so the pager shows up.
        for ($i = 2; $i <= 6; $i++) {
            $this->addTransaction($plan, 'HIST-'.$i);
        }

        $response = $this->actingAs($this->user())
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $plan, 'per_page' => 5]));

        $response->assertOk();
        $response->assertJsonCount(5, 'transactions');
        $response->assertJsonPath('pagination.last_page', 2);

        // The very same pagination markup the forum pages render.
        $html = (string) $response->json('pagination_html');

        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringContainsString('page-link', $html);
    }

    public function test_the_history_modal_uses_the_date_calendar_of_the_app(): void
    {
        [$plan] = $this->paidPlan();

        $this->actingAs($this->user());

        // The pushed scripts are flushed once the view is rendered, so they are
        // read through the render callback, while they are still there.
        $scripts = '';

        $html = view('components.property.payments', [
            'property' => $plan->propertyOwnerable,
        ])->render(function () use (&$scripts) {
            $scripts = app('view')->yieldPushContent('scripts');
        });

        // The from/to filters of the modal are date pickers (flatpickr).
        $this->assertStringContainsString('id="historyFrom"', $html);
        $this->assertStringContainsString('id="historyTo"', $html);
        $this->assertStringContainsString('tabler-calendar', $html);

        // The modal pages with the pagination of the app.
        $this->assertStringContainsString('id="historyPagination"', $html);

        // The script of the modal sets the date pickers up.
        $this->assertStringContainsString('initHistoryDatePickers', $scripts);
        $this->assertStringContainsString('flatpickr', $scripts);
    }

    public function test_the_receipt_downloads_as_a_pdf(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $response = $this->actingAs($this->user())
            ->get(route('ownership_payment_plans.payments.receipt.download', $txn));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringContainsString(
            'receipt-'.str_pad((string) $txn->id, 6, '0', STR_PAD_LEFT).'.pdf',
            $disposition
        );
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_the_receipt_download_requires_the_payment_collect_permission(): void
    {
        [$plan, $txn] = $this->paidPlan();

        $user = $this->user();
        $user->revokePermissionTo('payment_collect');

        $this->actingAs($user)
            ->get(route('ownership_payment_plans.payments.receipt.download', $txn))
            ->assertForbidden();
    }

    /**
     * A real Property row, so the payment plan's propertyOwnerable morph
     * resolves and "all payments" can find sibling plans through it.
     *
     * A fresh property per call keeps tests isolated from whatever data the
     * development database already holds.
     */
    private function property(): Property
    {
        $society = Society::create([
            'name' => 'History Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->user()->id,
        ]);

        $block = Block::create([
            'society_id' => $society->id,
            'name' => 'Block H',
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

    /**
     * A committed plan on $property with one recorded payment on it.
     *
     * Passing the same $property twice models a re-sold property: the first
     * call becomes the previous owner, the second the current one.
     *
     * @return array{0: \App\Models\PropertyReservedPaymentPlan, 1: \App\Models\PaymentTransaction}
     */
    private function planWithPayment(
        Property $property,
        string $reference,
        string $ownerName,
        float $paymentAmount = 50_000,
        bool $ended = false
    ): array {
        if (! FbrTaxRate::query()->exists()) {
            FbrTaxRate::create([
                'role' => 'buyer',
                'filer_status' => 'non_filer',
                'value_from' => 0,
                'value_to' => null,
                'rate_percent' => 5.00,
            ]);
        }

        $collector = $this->user();
        $buyer = $this->buyerNamed($ownerName);

        $owner = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => $property->id,
            'owner_id' => $buyer->id,
            'started_at' => now()->subYear()->toDateString(),
            'ended_at' => $ended ? now()->subMonth()->toDateString() : null,
            'status' => $ended ? 'cancelled' : 'active',
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

        $this->actingAs($collector)
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => $paymentAmount,
                'payment_method' => 'cash',
                'reference_no' => $reference,
            ]);

        return [$plan->fresh(), $plan->paymentTransactions()->orderByDesc('id')->first()];
    }

    /**
     * A recorded payment on a plan, without going through the collect form.
     *
     * Used to fill a plan with more transactions than fit on one page.
     */
    private function addTransaction(PropertyReservedPaymentPlan $plan, string $reference): void
    {
        PaymentTransaction::create([
            'ownership_payment_plan_id' => $plan->id,
            'amount_paid' => 10_000,
            'charge_subtotal' => 10_000,
            'tax_amount' => 0,
            'payment_method' => 'cash',
            'reference_no' => $reference,
            'recorded_by_user_id' => $this->user()->id,
            'paid_at' => now(),
            'status' => 'paid',
        ]);
    }

    /**
     * The single-plan helper the earlier tests share.
     */
    private function paidPlan(float $paymentAmount = 50_000): array
    {
        return $this->planWithPayment(
            property: $this->property(),
            reference: 'HIST-1',
            ownerName: 'Buyer',
            paymentAmount: $paymentAmount,
        );
    }

    /**
     * The buyer a plan is reserved for, as distinct from the staff user who
     * records the payment - history attributes each row to the buyer.
     */
    private function buyerNamed(string $firstName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => 'Buyer',
            'email' => strtolower($firstName).'.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
            'is_filer' => false,
        ]);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'History',
            'last_name' => 'Tester',
            'email' => 'history.'.uniqid().'@example.com',
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
