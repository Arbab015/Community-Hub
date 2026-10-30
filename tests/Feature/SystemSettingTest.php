<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\FbrTaxRate;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\Society;
use App\Models\SocietySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        FbrTaxRate::query()->delete();
    }

    public function test_index_defaults_to_the_first_owned_society(): void
    {
        $owner = $this->owner();
        $alpha = $this->society($owner, 'Alpha');
        $this->society($owner, 'Beta');

        $this->actingAs($owner)
            ->get(route('system-settings.index'))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee($alpha->name);
    }

    public function test_index_honours_the_selected_society(): void
    {
        $owner = $this->owner();
        $this->society($owner, 'Alpha');
        $beta = $this->society($owner, 'Beta');

        $this->actingAs($owner)
            ->get(route('system-settings.index', ['society_id' => $beta->id]))
            ->assertOk()
            ->assertSee('value="'.$beta->id.'" selected', false);
    }

    public function test_update_persists_the_setting_for_the_society(): void
    {
        $owner = $this->owner();
        $society = $this->society($owner, 'Alpha');

        $this->actingAs($owner)
            ->post(route('system-settings.update'), [
                'society_id' => $society->id,
                'settings' => [SocietySetting::INCLUDE_FBR_TAX => '0'],
            ])
            ->assertRedirect(route('system-settings.index', ['society_id' => $society->id]));

        $this->assertDatabaseHas('society_settings', [
            'society_id' => $society->id,
            'key' => SocietySetting::INCLUDE_FBR_TAX,
            'value' => '0',
        ]);

        $this->assertFalse(SocietySetting::includeFbrTax($society->id));
    }

    public function test_update_rejects_a_society_the_owner_does_not_own(): void
    {
        $owner = $this->owner();
        $this->society($owner, 'Alpha');

        $other = $this->owner();
        $foreign = $this->society($other, 'Foreign');

        $this->actingAs($owner)
            ->post(route('system-settings.update'), [
                'society_id' => $foreign->id,
                'settings' => [SocietySetting::INCLUDE_FBR_TAX => '0'],
            ])
            ->assertSessionHasErrors('society_id');
    }

    public function test_fbr_tax_is_not_charged_when_the_society_disables_it(): void
    {
        $owner = $this->owner();
        $society = $this->society($owner, 'Alpha');
        $plan = $this->planInSociety($society, 1_000_000);

        $this->rate(5.00);
        SocietySetting::set($society->id, SocietySetting::INCLUDE_FBR_TAX, false);

        $this->assertSame($society->id, $plan->societyId());
        $this->assertSame(0.0, $plan->fbrTaxRate());
    }

    public function test_fbr_tax_uses_the_slab_when_the_society_includes_it(): void
    {
        $owner = $this->owner();
        $society = $this->society($owner, 'Alpha');
        $plan = $this->planInSociety($society, 1_000_000);

        $this->rate(5.00);
        // No setting row -> default include, preserving previous behaviour.

        $this->assertSame($society->id, $plan->societyId());
        $this->assertSame(5.0, $plan->fbrTaxRate());
    }

    /* ------------------------------------------------------------------ */
    /* Helpers */
    /* ------------------------------------------------------------------ */

    private function owner(): User
    {
        $user = $this->user();
        $user->assignRole(Role::firstOrCreate(['name' => 'Society Owner']));
        $user->givePermissionTo('system_settings');

        return $user;
    }

    private function society(User $owner, string $name): Society
    {
        return Society::create([
            'owner_id' => $owner->id,
            'name' => $name,
            'address' => 'Test Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
        ]);
    }

    private function planInSociety(Society $society, float $finalPrice): PropertyReservedPaymentPlan
    {
        $block = Block::create(['society_id' => $society->id, 'name' => 'A']);

        $property = Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-1',
            'type' => 'house',
            'category' => 'residential',
            'street' => 'Street 1',
        ]);

        $ownership = PropertyOwner::create([
            'id' => (int) (DB::table('property_ownership_history')->max('id') ?? 0) + 1,
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $property->id,
            'owner_id' => $this->user()->id,
            'started_at' => now()->toDateString(),
        ]);

        return PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $property->id,
            'property_owner_id' => $ownership->id,
            'with_installment' => false,
            'final_price' => $finalPrice,
            'price_unit' => 'PKR',
            'status' => 'in-progress',
        ]);
    }

    private function rate(float $percent): FbrTaxRate
    {
        return FbrTaxRate::create([
            'role' => 'buyer',
            'filer_status' => 'non_filer',
            'value_from' => 0,
            'value_to' => null,
            'rate_percent' => $percent,
        ]);
    }

    private function user(): User
    {
        return User::create([
            'first_name' => 'Setting',
            'last_name' => 'Test',
            'email' => 'setting.'.uniqid().'@example.com',
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
