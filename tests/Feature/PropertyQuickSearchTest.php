<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\Society;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The navbar property search: a property is found by its name, its number or
 * the name of its owner, and the search never leaves the viewer's own world.
 */
class PropertyQuickSearchTest extends TestCase
{
    use DatabaseTransactions;

    private User $staff;

    private User $owner;

    private User $otherOwner;

    private User $outsider;

    private Society $society;

    private Property $property;

    private Property $otherProperty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = $this->makeUser('Staff', 'Approver');
        $this->staff->assignRole(Role::findOrCreate('Society Owner'));

        $this->owner = $this->makeUser('Bilal', 'Owner');
        $this->otherOwner = $this->makeUser('Sana', 'Owner');
        $this->outsider = $this->makeUser('Somebody', 'Elsewhere');

        $this->society = Society::create([
            'name' => 'Search Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->staff->id,
        ]);

        $block = Block::create([
            'society_id' => $this->society->id,
            'name' => 'Block A',
        ]);

        $this->property = $this->makeProperty($block->id, 'Palm Avenue', 'P-1001', $this->owner);

        $this->otherProperty = $this->makeProperty($block->id, 'Rose Villa', 'P-1002', $this->otherOwner);
    }

    public function test_the_search_finds_a_property_by_its_name(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property.quick_search', ['term' => 'Palm']));

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.uuid', $this->property->uuid);
        $response->assertJsonPath('results.0.property_no', 'P-1001');
    }

    public function test_the_search_finds_a_property_by_its_number(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property.quick_search', ['term' => 'P-1002']));

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.uuid', $this->otherProperty->uuid);
    }

    public function test_the_search_finds_a_property_by_the_owner_name(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property.quick_search', ['term' => 'Bilal']));

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.uuid', $this->property->uuid);
        $response->assertJsonPath('results.0.owner_name', 'Bilal Owner');
    }

    public function test_the_search_result_opens_the_property(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property.quick_search', ['term' => 'P-1001']));

        $response->assertOk();

        $url = $response->json('results.0.url');

        $this->assertSame(
            route('society.property.details', ['society_owners', $this->society->uuid, $this->property->uuid]),
            $url
        );

        $this->actingAs($this->staff)->get($url)->assertOk();
    }

    public function test_an_empty_term_returns_nothing(): void
    {
        $response = $this->actingAs($this->staff)
            ->getJson(route('property.quick_search', ['term' => '  ']));

        $response->assertOk();
        $response->assertJsonCount(0, 'results');
    }

    public function test_a_member_only_finds_their_own_property(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('property.quick_search', ['term' => 'P-1002']));

        $response->assertOk();
        $response->assertJsonCount(0, 'results');

        $response = $this->actingAs($this->owner)
            ->getJson(route('property.quick_search', ['term' => 'P-1001']));

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.url', route('owner.property.details', $this->property->uuid));
    }

    public function test_a_member_does_not_find_the_properties_of_another_society(): void
    {
        $otherSociety = Society::create([
            'name' => 'Other Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $this->outsider->id,
        ]);

        $otherBlock = Block::create([
            'society_id' => $otherSociety->id,
            'name' => 'Block Z',
        ]);

        $foreign = $this->makeProperty($otherBlock->id, 'Palm Bungalow', 'P-2001', $this->owner);

        $response = $this->actingAs($this->staff)
            ->getJson(route('property.quick_search', ['term' => 'P-2001']));

        $response->assertOk();
        $response->assertJsonCount(0, 'results');

        // The property itself is of course still there.
        $this->assertSame('P-2001', $foreign->fresh()->property_no);
    }

    public function test_the_navbar_opens_the_new_property_search_modal(): void
    {
        $response = $this->actingAs($this->staff)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();
        $response->assertSee('data-bs-target="#propertySearchModal"', false);
        $response->assertSee('Search by property name, property no or owner name', false);

        // The script of the modal is pushed into the page.
        $response->assertSee('propertySearchResults', false);
        $response->assertSee('quick-search', false);

        // The old autocomplete search is gone.
        $response->assertDontSee('id="autocomplete"', false);
    }

    public function test_the_modal_is_a_direct_child_of_the_body(): void
    {
        // Inside the navbar the modal is trapped in the stacking context of
        // `.layout-navbar` (z-index 2) and the bootstrap backdrop covers it.
        $response = $this->actingAs($this->staff)
            ->get(route('property.details', $this->property->uuid));

        $response->assertOk();

        $dom = new DOMDocument();

        $previous = libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml encoding="UTF-8">' . (string) $response->getContent());

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $modal = $dom->getElementById('propertySearchModal');

        $this->assertNotNull($modal, 'The property search modal is missing.');
        $this->assertSame('body', $modal->parentNode->nodeName);

        // It must be rendered exactly once.
        $xpath = new DOMXPath($dom);

        $this->assertSame(
            1,
            $xpath->query('//*[@id="propertySearchModal"]')->length,
            'The property search modal is not rendered exactly once.'
        );
    }

    private function makeProperty(int $blockId, string $name, string $propertyNo, User $owner): Property
    {
        $property = Property::create([
            'block_id' => $blockId,
            'name' => $name,
            'property_no' => $propertyNo,
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
            'owner_id' => $owner->id,
        ]);

        PropertyOwner::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $property->id,
            'owner_id' => $owner->id,
            'started_at' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        return $property;
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
