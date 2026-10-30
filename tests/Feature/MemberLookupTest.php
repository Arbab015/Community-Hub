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
 * The member lookup behind the "Reserve Property" and "Transfer Property"
 * modals: an existing member is anybody who already has a row in the `users`
 * table - the society they belong to is not part of the test.
 */
class MemberLookupTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $owner;

    private User $outsider;

    private Society $society;

    private Property $property;

    private PropertyOwner $ownership;

    private PropertyReservedPaymentPlan $sourcePlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->makeUser('Staff', 'Approver');
        $this->staff->assignRole(Role::findOrCreate('Society Owner'));
        $this->staff->givePermissionTo([
            'property_transfer_listing',
            'property_transfer_approve',
            'property_transfer_create',
            'payment_collect',
        ]);

        $this->owner = $this->makeUser('Outgoing', 'Owner');
        $this->outsider = $this->makeUser('Somebody', 'Elsewhere');

        $this->society = Society::create([
            'name' => 'Lookup Society',
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

        $this->property->owner_id = $this->owner->id;
        $this->property->save();

        $this->sourcePlan = PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $this->ownership->id,
            'with_installment' => false,
            'final_price' => 400_000,
            'price_unit' => 'PKR',
            'status' => 'completed',
        ]);

        // The owner belongs to the society, so they may transfer the property.
        $this->owner->memberSocieties()->syncWithoutDetaching([$this->society->id]);
    }

    public function test_the_lookup_finds_a_user_that_does_not_belong_to_the_society(): void
    {
        $this->assertFalse($this->outsider->memberSocieties()->exists());

        $response = $this->actingAs($this->staff)
            ->getJson(route('property_owners.lookup', ['term' => $this->outsider->email]));

        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'existing' => true,
            'user' => [
                'id' => $this->outsider->id,
                'email' => $this->outsider->email,
            ],
        ]);
    }

    public function test_the_lookup_finds_a_user_by_contact_number(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property_owners.lookup', ['term' => $this->outsider->contact]));

        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'user' => ['id' => $this->outsider->id],
        ]);
    }

    public function test_the_lookup_reports_nothing_for_an_unknown_term(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property_owners.lookup', ['term' => 'nobody@example.com']));

        $response->assertOk();
        $response->assertJson(['found' => false]);
    }

    public function test_a_transfer_can_target_an_existing_user_from_another_society(): void
    {
        $response = $this->actingAs($this->staff)
            ->postJson(route('property_transfers.store'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $this->property->id,
                'mode' => 'existing',
                'existing_user_id' => $this->outsider->id,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $transfer = PropertyTransfer::query()->latest('id')->firstOrFail();

        $this->assertSame($this->outsider->id, $transfer->recipient_user_id);
        $this->assertSame('pending', $transfer->status);

        // The recipient is attached to the society of the property.
        $this->assertTrue(
            $this->outsider->memberSocieties()->where('societies.id', $this->society->id)->exists()
        );
    }

    public function test_a_transfer_cannot_target_the_current_owner(): void
    {
        $response = $this->actingAs($this->staff)
            ->postJson(route('property_transfers.store'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $this->property->id,
                'mode' => 'existing',
                'existing_user_id' => $this->owner->id,
            ]);

        $response->assertStatus(422);
        $this->assertSame(0, PropertyTransfer::query()
            ->where('property_ownerable_id', $this->property->id)
            ->count());
    }

    public function test_a_property_can_be_reserved_for_a_user_from_another_society(): void
    {
        $property = Property::create([
            'block_id' => $this->property->block_id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 2',
        ]);

        $response = $this->actingAs($this->staff)
            ->postJson(route('reserve.property_owner.store'), [
                'ownerable_type' => Property::class,
                'ownerable_id' => $property->id,
                'mode' => 'existing',
                'existing_user_id' => $this->outsider->id,
                'society' => $this->society->id,
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertSame(
            $this->outsider->id,
            $property->fresh()->owner_id
        );

        // The new owner is attached to the society of the property.
        $this->assertTrue(
            $this->outsider->memberSocieties()->where('societies.id', $this->society->id)->exists()
        );
    }

    public function test_the_transfer_modal_does_not_demand_a_society_membership(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('society.property.details', [
                'user_type' => 'society_owners',
                'society_uuid' => $this->society->uuid,
                'uuid' => $this->property->uuid,
            ]));

        $response->assertOk();
        $response->assertSee('Any existing member of the platform can be picked');
        $response->assertDontSee('must already be a member of this society');
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
            'contact' => '0300'.random_int(1000000, 9999999),
            'is_filer' => false,
        ]);
    }
}
