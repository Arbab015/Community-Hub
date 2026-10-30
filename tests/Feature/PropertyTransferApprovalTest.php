<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyTransfer;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Approving a transfer request: the request becomes approved, ownership moves
 * to the recipient and the society transfer fee is billed to them as a charge
 * that can be paid from the payments view.
 */
class PropertyTransferApprovalTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $owner;

    private User $recipient;

    private Property $property;

    private Society $society;

    private PropertyOwner $ownership;

    private PropertyReservedPaymentPlan $sourcePlan;

    private PropertyTransfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->makeUser('Staff', 'Approver');
        $this->staff->assignRole(Role::findOrCreate('Society Owner'));
        $this->staff->givePermissionTo([
            'property_transfer_listing',
            'property_transfer_approve',
            'payment_collect',
        ]);

        $this->owner = $this->makeUser('Outgoing', 'Owner');
        $this->recipient = $this->makeUser('Incoming', 'Owner');

        $this->society = Society::create([
            'name' => 'Approval Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->staff->id,
        ]);

        $block = Block::create([
            'society_id' => $this->society->id,
            'name' => 'Block A',
        ]);

        $this->property = Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
        ]);

        $this->ownership = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'started_at' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $this->sourcePlan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $this->ownership->id,
            'with_installment' => false,
            'final_price' => 400_000,
            'price_unit' => 'PKR',
            'status' => 'completed',
        ]);

        $this->transfer = PropertyTransfer::create([
            'reference_no' => 'TRF-'.strtoupper(Str::random(8)),
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'from_ownership_id' => $this->ownership->id,
            'from_plan_id' => $this->sourcePlan->id,
            'requested_by_user_id' => $this->owner->id,
            'recipient_user_id' => $this->recipient->id,
            'society_id' => $this->society->id,
            'transfer_fee' => 15,
            'transfer_fee_in_per' => true,
            'fee_base' => 400_000,
            'fee_amount' => 60_000,
            'currency' => 'PKR',
            'status' => 'pending',
            'pending_marker' => (string) Str::uuid(),
        ]);
    }

    public function test_approving_the_request_sets_it_to_approved(): void
    {
        $this->actingAs($this->staff)
            ->post(route('property_transfers.approve', $this->transfer))
            ->assertRedirect();

        $this->transfer->refresh();

        $this->assertSame('approved', $this->transfer->status);
        $this->assertSame($this->staff->id, $this->transfer->acted_by_user_id);
        $this->assertNotNull($this->transfer->acted_at);
        $this->assertNull($this->transfer->pending_marker);
        $this->assertNotNull($this->transfer->new_plan_id);
    }

    public function test_the_approval_moves_the_ownership_to_the_recipient(): void
    {
        $this->approve();

        $this->ownership->refresh();
        $this->assertSame('transferred', $this->ownership->status->value);
        $this->assertNotNull($this->ownership->ended_at);

        $newOwnership = PropertyOwner::query()
            ->where('property_ownerable_id', $this->property->id)
            ->where('owner_id', $this->recipient->id)
            ->where('status', 'active')
            ->firstOrFail();

        $this->assertSame($this->transfer->id, $newOwnership->transfer_id);
    }

    public function test_the_approval_bills_the_transfer_fee_to_the_recipient(): void
    {
        $this->approve();

        $plan = PropertyReservedPaymentPlan::findOrFail($this->transfer->new_plan_id);

        $this->assertTrue($plan->isTransfer());
        $this->assertSame('active', $plan->status);
        $this->assertSame(60_000.0, (float) $plan->final_price);
        $this->assertSame($this->sourcePlan->id, $plan->parent_plan_id);

        $rows = $plan->ledgerRows()->get();

        $this->assertCount(1, $rows);
        $this->assertSame('transfer_fee', $rows->first()->charge_type);
        $this->assertSame('Transfer Fee', $rows->first()->display_name);
        $this->assertSame(60_000.0, (float) $rows->first()->amount_due);
        $this->assertSame(0.0, (float) $rows->first()->amount_paid);
        $this->assertNotSame('paid', $rows->first()->payment_status);
    }

    public function test_the_transfer_fee_can_be_collected_after_the_approval(): void
    {
        $this->approve();

        $plan = PropertyReservedPaymentPlan::findOrFail($this->transfer->new_plan_id);

        $this->actingAs($this->staff)
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 60_000,
                'payment_method' => 'cash',
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $row = $plan->ledgerRows()->firstOrFail();

        $this->assertSame(60_000.0, (float) $row->amount_paid);
        $this->assertSame('paid', $row->payment_status);

        $plan->refresh();
        $this->assertSame('completed', $plan->status);

        // The transfer fee is not taxable.
        $this->assertDatabaseHas('payment_transactions', [
            'ownership_payment_plan_id' => $plan->id,
            'amount_paid' => 60_000,
            'tax_amount' => 0,
        ]);
    }

    public function test_the_transfers_list_asks_for_sweet_alert_confirmation_before_approving(): void
    {
        $response = $this->actingAs($this->staff)->get(route('property_transfers.index'));

        $response->assertOk();
        $response->assertSee('js-approve-transfer', false);
        $response->assertDontSee("onsubmit=\"return confirm('Approve this transfer", false);
    }

    public function test_the_transfers_list_shows_the_fee_and_how_to_collect_it(): void
    {
        $this->approve();

        $response = $this->actingAs($this->staff)->get(route('property_transfers.index'));

        $response->assertOk();
        $response->assertSee($this->transfer->reference_no);
        $response->assertSee('Approved');
        $response->assertSee('Outstanding');
        $response->assertSee('Collect Fee');
    }

    public function test_the_transfers_list_marks_the_fee_as_paid_once_it_is_collected(): void
    {
        $this->approve();

        $plan = PropertyReservedPaymentPlan::findOrFail($this->transfer->new_plan_id);

        $this->actingAs($this->staff)
            ->post(route('payment.store', $plan), [
                'payment_mode' => 'amount',
                'amount' => 60_000,
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $response = $this->actingAs($this->staff)->get(route('property_transfers.index'));

        $response->assertOk();
        $response->assertSee('Fee paid');
    }

    public function test_the_transfers_list_searches_property_and_people_in_one_field(): void
    {
        $this->property->update(['name' => 'Sea Breeze Villa']);

        foreach ([
            'Sea Breeze',
            $this->property->property_no,
            'Outgoing',
            'Incoming',
            $this->recipient->email,
        ] as $term) {
            $response = $this->actingAs($this->staff)
                ->get(route('property_transfers.index', ['q' => $term]));

            $response->assertOk();
            $response->assertSee($this->transfer->reference_no);
            $response->assertViewHas('transfers', fn ($transfers) => $transfers->total() === 1);
        }

        $this->actingAs($this->staff)
            ->get(route('property_transfers.index'))
            ->assertSee("Searches property name, property no, unit name, and the from/to member's name or", false);
    }

    public function test_the_transfers_list_search_hides_non_matching_transfers(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('property_transfers.index', ['q' => 'not-a-real-transfer']));

        $response->assertOk();
        $response->assertDontSee($this->transfer->reference_no);
        $response->assertSee('No transfers for your societies yet.');
        $response->assertViewHas('transfers', fn ($transfers) => $transfers->total() === 0);
    }

    public function test_the_incoming_owner_sees_the_fee_as_payable(): void
    {
        $this->approve();

        $response = $this->actingAs($this->recipient)
            ->get(route('society.property.details', [
                'user_type' => 'society_members',
                'society_uuid' => $this->society->uuid,
                'uuid' => $this->property->uuid,
            ]));

        $response->assertOk();
        $response->assertSee('Society Transfer Fee');
        $response->assertSee('60,000.00');
        $response->assertSee('payable to the society');
        $response->assertDontSee('must be complete before the property can be transferred');
    }

    public function test_the_transfer_plan_stores_the_listed_price_of_the_property(): void
    {
        $this->approve();

        $plan = PropertyReservedPaymentPlan::findOrFail($this->transfer->new_plan_id);

        // The fee is a percentage of the listed price - it is stored with the
        // plan so the reserved view can show both side by side.
        $this->assertSame(400_000.0, (float) $plan->listed_price);
    }

    public function test_the_reserved_plan_of_a_transfer_shows_the_listed_price_the_fee_and_the_final_amount(): void
    {
        $this->approve();

        $response = $this->actingAs($this->recipient)
            ->get(route('society.property.details', [
                'user_type' => 'society_members',
                'society_uuid' => $this->society->uuid,
                'uuid' => $this->property->uuid,
            ]));

        $response->assertOk();

        // Listed price of the property, then the transfer fee.
        $response->assertSee('Listed Price');
        $response->assertSee('400,000.00');
        $response->assertSee('Transfer Fee');

        // The gross amount of a transfer plan is the fee itself...
        $response->assertSee('Gross Amount');
        $response->assertSee('60,000');

        // ...and it is included in the gross amount as a one time charge.
        $response->assertSee('Included Charges');
        $response->assertSee('Final Amount');
    }

    public function test_the_reserved_plan_of_a_transfer_has_no_edit_or_installment_controls(): void
    {
        $this->approve();

        // The staff may edit regular plans - a transfer plan is still read only.
        $this->staff->givePermissionTo([
            'payment_plan_edit',
            'installment_plan_add',
        ]);

        $response = $this->actingAs($this->staff)
            ->get(route('society.property.details', [
                'user_type' => 'society_owners',
                'society_uuid' => $this->society->uuid,
                'uuid' => $this->property->uuid,
            ]));

        $response->assertOk();
        $response->assertSee('Transfer Fee');
        $response->assertDontSee('Edit Values');
        $response->assertDontSee('Add Installment Plan');
    }

    public function test_a_regular_reserved_plan_still_offers_the_edit_and_installment_controls(): void
    {
        $this->staff->givePermissionTo([
            'payment_plan_edit',
            'installment_plan_add',
        ]);

        // The controls only show while payments are still due.
        $this->sourcePlan->update(['status' => 'in-progress']);

        $response = $this->actingAs($this->staff)
            ->get(route('society.property.details', [
                'user_type' => 'society_owners',
                'society_uuid' => $this->society->uuid,
                'uuid' => $this->property->uuid,
            ]));

        $response->assertOk();
        $response->assertSee('Edit Values');
        $response->assertSee('Add Installment Plan');
    }

    public function test_rejecting_the_request_creates_no_charge(): void
    {
        $this->actingAs($this->staff)
            ->post(route('property_transfers.reject', $this->transfer), [
                'remarks' => 'Not allowed',
            ])
            ->assertRedirect();

        $this->transfer->refresh();

        $this->assertSame('rejected', $this->transfer->status);
        $this->assertNull($this->transfer->new_plan_id);

        $this->assertSame(0, PropertyReservedPaymentPlan::query()
            ->where('plan_type', 'transfer')
            ->where('property_ownerable_id', $this->property->id)
            ->count());
    }

    private function approve(): void
    {
        $this->actingAs($this->staff)
            ->post(route('property_transfers.approve', $this->transfer))
            ->assertRedirect();

        $this->transfer->refresh();
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
}
