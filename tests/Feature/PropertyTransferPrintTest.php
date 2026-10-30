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
use Tests\TestCase;

/**
 * The printable transfer slip: the document the society owner, the outgoing
 * owner and the incoming owner use as the paper trail of a transfer.
 */
class PropertyTransferPrintTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $owner;

    private User $recipient;

    private User $stranger;

    private Property $property;

    private Society $society;

    private PropertyTransfer $transfer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->makeUser('Staff', 'Owner');
        $this->staff->givePermissionTo([
            'property_transfer_listing',
            'property_transfer_approve',
            'property_transfer_print',
        ]);

        $this->owner = $this->makeUser('Outgoing', 'Owner');
        $this->recipient = $this->makeUser('Incoming', 'Owner');
        $this->stranger = $this->makeUser('No', 'Relation');

        $this->society = Society::create([
            'name' => 'Transfer Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->staff->id,
        ]);

        $block = Block::create([
            'society_id' => $this->society->id,
            'name' => 'Block T',
        ]);

        $this->property = Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
        ]);

        $ownership = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'started_at' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        $plan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $ownership->id,
            'with_installment' => false,
            'final_price' => 100_000,
            'price_unit' => 'PKR',
            'status' => 'completed',
        ]);

        $this->transfer = PropertyTransfer::create([
            'reference_no' => 'TRF-'.strtoupper(Str::random(8)),
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'from_ownership_id' => $ownership->id,
            'from_plan_id' => $plan->id,
            'requested_by_user_id' => $this->owner->id,
            'recipient_user_id' => $this->recipient->id,
            'society_id' => $this->society->id,
            'transfer_fee' => 25_000,
            'transfer_fee_in_per' => false,
            'fee_base' => 100_000,
            'fee_amount' => 25_000,
            'currency' => 'PKR',
            'status' => 'pending',
            'pending_marker' => (string) Str::uuid(),
        ]);
    }

    public function test_the_transfer_slip_renders(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('property_transfers.print', $this->transfer));

        $response->assertOk();
        $response->assertSee('PROPERTY TRANSFER SLIP');
        $response->assertSee($this->transfer->reference_no);
        $response->assertSee('Outgoing Owner');
        $response->assertSee('Incoming Owner');
        $response->assertSee('25,000.00');
    }

    public function test_the_slip_prints_automatically_when_asked(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('property_transfers.print', ['transfer' => $this->transfer, 'print' => 1]));

        $response->assertOk();
        $response->assertSee('data-receipt-auto-print', false);
    }

    public function test_the_requesting_owner_and_the_recipient_may_print_their_own_transfer(): void
    {
        $this->actingAs($this->owner)
            ->get(route('property_transfers.print', $this->transfer))
            ->assertOk();

        $this->actingAs($this->recipient)
            ->get(route('property_transfers.print', $this->transfer))
            ->assertOk();
    }

    public function test_staff_needs_the_print_permission_to_print_a_transfer(): void
    {
        // Listing and approving a transfer is not enough to print its slip.
        $this->staff->revokePermissionTo('property_transfer_print');

        $this->actingAs($this->staff)
            ->get(route('property_transfers.print', $this->transfer))
            ->assertForbidden();
    }

    public function test_an_unrelated_member_may_not_print_the_transfer(): void
    {
        $this->actingAs($this->stranger)
            ->get(route('property_transfers.print', $this->transfer))
            ->assertForbidden();
    }

    public function test_an_approved_transfer_prints_the_incoming_transfer_fee_plan(): void
    {
        // The ownership the transfer creates, plus the transfer-fee plan on it.
        // The outgoing ownership ends first - only one ownership of a property
        // may be active at a time.
        $this->transfer->fromOwnership->update([
            'ended_at' => now()->toDateString(),
            'status' => 'transferred',
        ]);

        $newOwnership = PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'owner_id' => $this->recipient->id,
            'started_at' => now()->toDateString(),
            'status' => 'active',
            'transfer_id' => $this->transfer->id,
        ]);

        $newPlan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $newOwnership->id,
            'plan_type' => 'transfer',
            'parent_plan_id' => $this->transfer->from_plan_id,
            'fee_base' => 100_000,
            'transfer_fee' => 25_000,
            'final_price' => 25_000,
            'price_unit' => 'PKR',
            'status' => 'active',
        ]);

        $this->transfer->update([
            'status' => 'approved',
            'acted_by_user_id' => $this->staff->id,
            'acted_at' => now(),
            'pending_marker' => null,
            'new_plan_id' => $newPlan->id,
        ]);

        $response = $this->actingAs($this->staff)
            ->get(route('property_transfers.print', $this->transfer));

        $response->assertOk();
        $response->assertSee('Approved');
        $response->assertSee('transfer-fee plan');
        $response->assertSee('Outstanding');
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
