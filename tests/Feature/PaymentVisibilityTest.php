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
use App\Services\Property\PropertyTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Who may see which payment records on a property.
 *
 * Staff (society owner, admins) see every ownership the property has ever
 * had. A society member only ever sees the plans and payments of their OWN
 * ownership: the reservation plan they bought the property on, or the
 * transfer-fee plan they were handed when the property changed hands.
 */
class PaymentVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private User $societyOwner;

    private User $buyer;

    private User $otherBuyer;

    private User $outsider;

    private Property $property;

    private Society $society;

    protected function setUp(): void
    {
        parent::setUp();

        FbrTaxRate::query()->delete();

        $this->societyOwner = $this->makeUser('Staff', 'Owner');
        $this->societyOwner->givePermissionTo([
            'payment_collect', 'payment_history', 'payment_history_switch', 'property_transfer_approve',
        ]);

        $this->buyer = $this->makeMember('First', 'Buyer');
        $this->otherBuyer = $this->makeMember('Second', 'Buyer');
        $this->outsider = $this->makeMember('No', 'Property');

        $this->society = Society::create([
            'name' => 'Visibility Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->societyOwner->id,
        ]);

        $block = Block::create([
            'society_id' => $this->society->id,
            'name' => 'Block V',
        ]);

        $this->property = Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* The property page                                                  */
    /* ------------------------------------------------------------------ */

    public function test_a_society_member_sees_only_the_plans_of_their_own_ownership(): void
    {
        $this->ownershipWithPlan($this->buyer, 'Own Installment');
        $this->ownershipWithPlan($this->otherBuyer, 'Other Installment', ended: true);

        $response = $this->actingAs($this->buyer)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('Own Installment');
        $response->assertDontSee('Other Installment');
    }

    public function test_staff_sees_every_ownership_of_the_property(): void
    {
        $this->ownershipWithPlan($this->buyer, 'Own Installment');
        $this->ownershipWithPlan($this->otherBuyer, 'Other Installment', ended: true);

        $response = $this->actingAs($this->societyOwner)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('Own Installment');
        $response->assertSee('Other Installment');
    }

    public function test_a_member_without_an_ownership_is_told_there_are_no_payments_yet(): void
    {
        $this->ownershipWithPlan($this->buyer, 'Own Installment');

        $response = $this->actingAs($this->outsider)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('No ownership payments yet');
        $response->assertDontSee('Own Installment');
    }

    /* ------------------------------------------------------------------ */
    /* The payment history behind the modal                               */
    /* ------------------------------------------------------------------ */

    public function test_a_society_member_cannot_read_another_owners_payment_history(): void
    {
        $ownPlan = $this->ownershipWithPlan($this->buyer, 'Own Installment');
        $otherPlan = $this->ownershipWithPlan($this->otherBuyer, 'Other Installment', ended: true);

        $this->actingAs($this->buyer)
            ->getJson(route('ownership_payment_plans.payments.history', $otherPlan))
            ->assertForbidden();

        // Their own plan stays readable.
        $this->actingAs($this->buyer)
            ->getJson(route('ownership_payment_plans.payments.history', $ownPlan))
            ->assertOk();
    }

    public function test_a_society_member_only_gets_their_own_payments_in_the_widened_scope(): void
    {
        $ownPlan = $this->ownershipWithPlan($this->buyer, 'Own Installment');
        $otherPlan = $this->ownershipWithPlan($this->otherBuyer, 'Other Installment', ended: true);

        $this->recordPayment($ownPlan, 'OWN-1');
        $this->recordPayment($otherPlan, 'OTHER-1');

        $data = $this->actingAs($this->buyer)
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $ownPlan, 'scope' => 'all']))
            ->assertOk()
            ->json();

        $this->assertSame(['OWN-1'], collect($data['transactions'])->pluck('reference_no')->all());
    }

    public function test_staff_gets_every_payment_in_the_widened_scope(): void
    {
        $ownPlan = $this->ownershipWithPlan($this->buyer, 'Own Installment');
        $otherPlan = $this->ownershipWithPlan($this->otherBuyer, 'Other Installment', ended: true);

        $this->recordPayment($ownPlan, 'OWN-1');
        $this->recordPayment($otherPlan, 'OTHER-1');

        $data = $this->actingAs($this->societyOwner)
            ->getJson(route('ownership_payment_plans.payments.history', ['snapshot' => $ownPlan, 'scope' => 'all']))
            ->assertOk()
            ->json();

        $this->assertEqualsCanonicalizing(
            ['OWN-1', 'OTHER-1'],
            collect($data['transactions'])->pluck('reference_no')->all()
        );
    }

    public function test_a_society_member_cannot_download_the_receipt_of_another_owners_payment(): void
    {
        $otherPlan = $this->ownershipWithPlan($this->otherBuyer, 'Other Installment', ended: true);
        $transaction = $this->recordPayment($otherPlan, 'OTHER-1');

        $this->actingAs($this->buyer)
            ->get(route('ownership_payment_plans.payments.receipt.download', $transaction))
            ->assertForbidden();
    }

    /* ------------------------------------------------------------------ */
    /* After a transfer                                                    */
    /* ------------------------------------------------------------------ */

    public function test_the_new_owner_sees_only_the_transfer_plan_after_the_property_changes_hands(): void
    {
        $this->transferProperty();

        $response = $this->actingAs($this->otherBuyer)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('Transfer Fee');
        $response->assertDontSee('Installment #1');
    }

    public function test_the_previous_owner_keeps_reading_their_own_records_after_the_transfer(): void
    {
        $this->transferProperty();

        $response = $this->actingAs($this->buyer)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('Installment #1');
        $response->assertDontSee('Transfer Fee');
        $response->assertSee('This property is no longer yours');
    }

    public function test_staff_sees_the_transfer_plan_and_the_previous_ownership_side_by_side(): void
    {
        $this->transferProperty();

        $response = $this->actingAs($this->societyOwner)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('Transfer Fee');
        $response->assertSee('Installment #1');
    }

    public function test_the_new_owner_can_print_the_transfer_slip_from_the_payments_tab(): void
    {
        $this->transferProperty();

        // The transfer-fee plan belongs to the incoming ownership, so the slip
        // of that transfer is the incoming owner's paper trail.
        $this->actingAs($this->otherBuyer)
            ->get(route('property.details', $this->property->uuid))
            ->assertOk()
            ->assertSee('Print Transfer Slip');

        // The outgoing owner sees their own (reservation) plan only.
        $this->actingAs($this->buyer)
            ->get(route('property.details', $this->property->uuid))
            ->assertOk()
            ->assertDontSee('Print Transfer Slip');
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Hand the property over from the first buyer to the second one, with a
     * 25,000 society transfer fee frozen onto the transfer.
     */
    private function transferProperty(): void
    {
        $plan = $this->ownershipWithPlan($this->buyer, 'Installment #1');

        // Settle the plan in full - a property can only change hands once it
        // has been paid for.
        $this->recordPayment($plan, 'PAID-1', 100_000);

        $this->assertSame('completed', $plan->fresh()->status);

        $service = app(PropertyTransferService::class);

        $transfer = $service->create(
            $this->property,
            $plan,
            $plan->propertyOwner,
            $this->buyer,
            $this->otherBuyer,
            [
                'society_id' => $this->society->id,
                'fee_id' => null,
                'is_percentage' => false,
                'percent' => 0.0,
                'base' => 100_000.0,
                'amount' => 25_000.0,
                'currency' => 'PKR',
                'matched' => true,
            ]
        );

        $service->approve($transfer, $this->societyOwner);
    }

    /**
     * An ownership of this property with one committed plan and one charge.
     */
    private function ownershipWithPlan(User $owner, string $chargeName, bool $ended = false): PropertyReservedPaymentPlan
    {
        $ownership = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'owner_id' => $owner->id,
            'started_at' => now()->subMonth()->toDateString(),
            'ended_at' => $ended ? now()->subDay()->toDateString() : null,
            'status' => $ended ? 'cancelled' : 'active',
        ]);

        $plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $ownership->id,
            'plan_type' => 'reservation',
            'with_installment' => false,
            'listed_price' => 100_000,
            'final_price' => 100_000,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);

        PropertyPaymentLedger::create([
            'ownership_payment_plan_id' => $plan->id,
            'charge_type' => 'installment',
            'display_name' => $chargeName,
            'sequence_order' => 1,
            'installment_no' => 1,
            'amount_due' => 100_000,
            'amount_paid' => 0,
            'remaining_balance' => 100_000,
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        return $plan;
    }

    /**
     * Record a payment against a plan, as the society staff would.
     */
    private function recordPayment(PropertyReservedPaymentPlan $plan, string $reference, float $amount = 10_000)
    {
        $this->actingAs($this->societyOwner)
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => $amount,
                'payment_method' => 'cash',
                'reference_no' => $reference,
            ])
            ->assertSessionHas('success');

        return $plan->paymentTransactions()->orderByDesc('id')->first();
    }

    private function makeUser(string $firstName, string $lastName): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => strtolower($firstName.$lastName).'.'.uniqid().'@example.com',
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

    /**
     * A society member: the role a buyer or a transfer recipient carries.
     */
    private function makeMember(string $firstName, string $lastName): User
    {
        $user = $this->makeUser($firstName, $lastName);

        $user->assignRole(Role::firstOrCreate(['name' => 'Society Member']));
        $user->givePermissionTo('payment_history');

        return $user;
    }
}
