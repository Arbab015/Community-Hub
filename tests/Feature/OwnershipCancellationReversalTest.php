<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\FbrTaxRate;
use App\Models\OwnershipCancellationReversal;
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

/**
 * Cancelling an ownership reservation with a return.
 *
 * The behaviour locked down here is the reason the cancellation record exists
 * at all: the money the buyer paid (charges + FBR tax) is returned, an optional
 * commission/reduction is applied, a printable receipt is produced - and the
 * payment records are NEVER deleted, only marked as returned.
 */
class OwnershipCancellationReversalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        FbrTaxRate::query()->delete();
        FbrTaxRate::create([
            'role' => 'buyer',
            'filer_status' => 'non_filer',
            'value_from' => 0,
            'value_to' => null,
            'rate_percent' => 5.00,
        ]);
    }

    public function test_summary_reports_the_paid_amount_tax_and_commission_base(): void
    {
        [$property] = $this->reservedPropertyWithPayment(50_000);

        $this->actingAs($this->staff())
            ->getJson(route('property_owners.cancellation.summary', [
                'ownerable_type' => Property::class,
                'ownerable_id' => $property->id,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('return_amount', fn ($v) => (float) $v === 50_000.0)
            ->assertJsonPath('return_tax', fn ($v) => (float) $v === 2_500.0)
            ->assertJsonPath('return_total', fn ($v) => (float) $v === 52_500.0)
            ->assertJsonPath('commission_base', fn ($v) => (float) $v === 100_000.0)
            ->assertJsonPath('has_payment', true)
            ->assertJsonPath('payment_count', 1);
    }

    public function test_cancelling_returns_the_paid_amount_without_deleting_payments(): void
    {
        [$property, $plan, $txn] = $this->reservedPropertyWithPayment(50_000);

        $staff = $this->staff();
        $txnCountBefore = PaymentTransaction::query()->count();

        $this->actingAs($staff)
            ->post(route('property_owners.cancel_reservation_settlement'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $property->id,
                'commission_mode' => 'percentage',
                'commission_value' => 2,
                'remarks' => 'Buyer changed their mind',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect()
            ->assertSessionHas('print_document_url', function (string $url) {
                return str_contains($url, 'cancellation-receipt') && str_contains($url, 'print=1');
            });

        // The payment record must survive - flipped, never deleted.
        $this->assertSame($txnCountBefore, PaymentTransaction::query()->count());
        $this->assertSame($txnCountBefore, PaymentTransaction::withTrashed()->count());

        $txn->refresh();
        $this->assertSame('returned', $txn->status);

        // The plan is closed and every charge that carried a payment is closed
        // as reversed. A charge that was never paid stays as it was - there is
        // nothing to hand back for it.
        $this->assertSame('reversed', $plan->fresh()->status);
        $reversedRows = PropertyPaymentLedger::query()
            ->where('ownership_payment_plan_id', $plan->id)
            ->where('payment_status', 'reversed')
            ->get();
        $this->assertCount(1, $reversedRows);
        $this->assertSame(0.0, (float) $reversedRows->first()->amount_paid);

        // The cancellation record captured the mandatory return and the commission.
        $reversal = OwnershipCancellationReversal::query()->where('plan_id', $plan->id)->firstOrFail();
        $this->assertSame('returned', $reversal->status);
        $this->assertSame('ownership_cancel_return', $reversal->type);
        $this->assertNotNull($reversal->reason);
        $this->assertSame($staff->id, $reversal->reversed_by);
        $this->assertSame(50_000.0, (float) $reversal->return_amount);
        $this->assertSame(2_500.0, (float) $reversal->return_tax);
        $this->assertSame(52_500.0, (float) $reversal->return_total);
        $this->assertSame('percentage', $reversal->commission_type);
        $this->assertSame(100_000.0, (float) $reversal->commission_base);
        $this->assertSame(2_000.0, (float) $reversal->commission_amount);
        $this->assertSame('PKR', $reversal->currency);

        // Ownership ended and the property relisted owner-less.
        $this->assertNotNull($plan->propertyOwner()->first()->ended_at);
        $this->assertNull($property->fresh()->owner_id);
    }

    public function test_a_fixed_amount_commission_is_stored_as_typed(): void
    {
        [$property, $plan] = $this->reservedPropertyWithPayment(50_000);

        $this->actingAs($this->staff())
            ->post(route('property_owners.cancel_reservation_settlement'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $property->id,
                'commission_mode' => 'amount',
                'commission_value' => 3_500,
            ])
            ->assertSessionHasNoErrors();

        $reversal = OwnershipCancellationReversal::query()->where('plan_id', $plan->id)->firstOrFail();
        $this->assertSame('amount', $reversal->commission_type);
        $this->assertSame(3_500.0, (float) $reversal->commission_amount);
    }

    public function test_the_cancellation_receipt_renders_the_returned_amount(): void
    {
        [$property, $plan] = $this->reservedPropertyWithPayment(50_000);
        $staff = $this->staff();

        $this->actingAs($staff)
            ->post(route('property_owners.cancel_reservation_settlement'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $property->id,
                'commission_mode' => 'percentage',
                'commission_value' => 2,
            ])
            ->assertSessionHasNoErrors();

        $reversal = OwnershipCancellationReversal::query()->where('plan_id', $plan->id)->firstOrFail();

        $response = $this->actingAs($staff)
            ->get(route('property_owners.cancellation.receipt', $reversal));

        $response->assertOk();
        // Gross paid: 50,000 charges + 2,500 FBR tax; 2% of 100,000 = 2,000
        // reduction; net handed back to the owner = 50,500.
        $response->assertSee('50,000.00');
        $response->assertSee('2,500.00');
        $response->assertSee('2,000.00');
        $response->assertSee('50,500.00');
    }

    public function test_cancellation_requires_the_property_reserve_owner_permission(): void
    {
        [$property, $plan, $txn] = $this->reservedPropertyWithPayment(50_000);

        $outsider = $this->makeUser('No', 'Permission');

        $this->actingAs($outsider)
            ->post(route('property_owners.cancel_reservation_settlement'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $property->id,
            ])
            ->assertForbidden();

        $this->assertSame('paid', $txn->fresh()->status);
        $this->assertSame(0, OwnershipCancellationReversal::query()->where('plan_id', $plan->id)->count());
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    /**
     * A real, owner-assigned property with a committed plan and one payment.
     *
     * @return array{0: Property, 1: PropertyReservedPaymentPlan, 2: PaymentTransaction}
     */
    private function reservedPropertyWithPayment(float $paymentAmount): array
    {
        $staff = $this->staff();

        $society = Society::create([
            'name' => 'Cancel Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $staff->id,
        ]);

        $block = Block::create([
            'society_id' => $society->id,
            'name' => 'Block C',
        ]);

        $property = Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
            'owner_id' => $this->makeUser('Buyer', 'Owner')->id,
        ]);

        $owner = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => 'App\\Models\\Property',
            'property_ownerable_id' => $property->id,
            'owner_id' => $property->owner_id,
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

        $this->actingAs($staff)->post(route('payment.store', $plan), [
            'payment_mode' => 'amount',
            'amount' => $paymentAmount,
            'payment_method' => 'cash',
            'reference_no' => 'CANCEL-'.uniqid(),
        ])->assertSessionHasNoErrors();

        $txn = $plan->paymentTransactions()->orderByDesc('id')->firstOrFail();

        return [$property, $plan, $txn];
    }

    private function staff(): User
    {
        $user = $this->makeUser('Cancel', 'Staff');
        $user->givePermissionTo(['payment_collect', 'property_reserve_owner']);

        return $user;
    }

    private function makeUser(string $first, string $last): User
    {
        return User::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($first).'.'.uniqid().'@example.com',
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
}
