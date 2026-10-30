<?php

namespace Tests\Feature;

use App\Models\FbrTaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class FbrTaxRateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_index_creates_the_base_slab_for_a_role_that_has_none(): void
    {
        FbrTaxRate::query()->delete();

        $this->actingAs($this->user())->get(route('fbr-tax-rates.index'))->assertOk();

        $this->assertSame(4, FbrTaxRate::count());

        foreach (['buyer', 'seller'] as $role) {
            foreach (['filer', 'non_filer'] as $status) {
                $this->assertSame(1, FbrTaxRate::where('role', $role)
                    ->where('filer_status', $status)
                    ->where('value_from', 0)
                    ->whereNull('value_to')
                    ->count());
            }
        }
    }

    public function test_index_tops_up_a_slab_that_is_missing_its_counterpart(): void
    {
        FbrTaxRate::query()->delete();

        FbrTaxRate::create([
            'role' => 'seller',
            'filer_status' => 'filer',
            'value_from' => 5_000_000,
            'value_to' => null,
            'rate_percent' => 2,
        ]);

        $this->actingAs($this->user())->get(route('fbr-tax-rates.index'))->assertOk();

        $slab = FbrTaxRate::where('role', 'seller')->where('value_from', 5_000_000)->first();

        $this->assertSame(2, (int) $slab->rate_percent);
        $this->assertSame(1, FbrTaxRate::where('role', 'seller')
            ->where('filer_status', 'non_filer')
            ->where('value_from', 5_000_000)
            ->whereNull('value_to')
            ->count());
    }

    public function test_index_route_renders_for_user_with_permission(): void
    {
        $response = $this->actingAs($this->user())->get(route('fbr-tax-rates.index'));

        $response->assertOk();
        $response->assertSee('FBR Tax Rates');
        $response->assertSee('Buyer Tax Rates');
        $response->assertSee('Seller Tax Rates');
    }

    public function test_store_creates_filer_and_non_filer_pair(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'seller', 'value_from' => 10_000_000, 'value_to' => 20_000_000, 'filers_rate' => 3, 'non_filers_rate' => 6])
            ->assertRedirect(route('fbr-tax-rates.index'))
            ->assertSessionHasNoErrors();

        $pair = FbrTaxRate::where('role', 'seller')->where('value_from', 10_000_000)->where('value_to', 20_000_000)->get();

        $this->assertCount(2, $pair);
        $this->assertSame([3, 6], $pair->sortBy('filer_status', SORT_NATURAL)->pluck('rate_percent')->map(fn ($r) => (int) $r)->all());
    }

    public function test_store_rejects_an_exact_duplicate_range(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'buyer', 'value_from' => 10_000_000, 'value_to' => 20_000_000, 'filers_rate' => 3, 'non_filers_rate' => 6]);
        $this->store(['role' => 'buyer', 'value_from' => 10_000_000, 'value_to' => 20_000_000, 'filers_rate' => 4, 'non_filers_rate' => 7])
            ->assertSessionHasErrors('value_from');

        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_store_rejects_a_start_amount_that_is_already_used_by_another_slab(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'buyer', 'value_from' => 0, 'value_to' => 50_000_000, 'filers_rate' => 1.25, 'non_filers_rate' => 10.5]);
        $this->store(['role' => 'buyer', 'value_from' => 50_000_000, 'value_to' => 100_000_000, 'filers_rate' => 1.25, 'non_filers_rate' => 14.5]);

        $this->store(['role' => 'buyer', 'value_from' => 0, 'value_to' => 80_000_000, 'filers_rate' => 4, 'non_filers_rate' => 7])
            ->assertSessionHasErrors('value_from');

        $this->assertSame(
            'A buyer slab already starts at PKR 0 (PKR 0 - 50,000,000).',
            session('errors')->first('value_from')
        );

        $this->assertSame(4, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_store_rejects_a_range_that_overlaps_an_existing_slab(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'buyer', 'value_from' => 0, 'value_to' => 50_000_000, 'filers_rate' => 1.25, 'non_filers_rate' => 10.5]);
        $this->store(['role' => 'buyer', 'value_from' => 25_000_000, 'value_to' => 75_000_000, 'filers_rate' => 3, 'non_filers_rate' => 6])
            ->assertSessionHasErrors('value_from');

        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_store_rejects_an_and_above_slab_that_swallows_an_existing_slab(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'buyer', 'value_from' => 0, 'value_to' => 50_000_000, 'filers_rate' => 1.25, 'non_filers_rate' => 10.5]);
        $this->store(['role' => 'buyer', 'value_from' => 40_000_000, 'value_to' => null, 'filers_rate' => 3, 'non_filers_rate' => 6])
            ->assertSessionHasErrors('value_from');

        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_store_allows_a_range_that_touches_the_previous_slab(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'buyer', 'value_from' => 0, 'value_to' => 50_000_000, 'filers_rate' => 1.25, 'non_filers_rate' => 10.5]);
        $this->store(['role' => 'buyer', 'value_from' => 50_000_000, 'value_to' => 100_000_000, 'filers_rate' => 1.25, 'non_filers_rate' => 14.5])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_store_allows_the_same_range_on_the_other_role(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'buyer', 'value_from' => 10_000_000, 'value_to' => 20_000_000, 'filers_rate' => 3, 'non_filers_rate' => 6])
            ->assertSessionHasNoErrors();
        $this->store(['role' => 'seller', 'value_from' => 10_000_000, 'value_to' => 20_000_000, 'filers_rate' => 3, 'non_filers_rate' => 6])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->count());
        $this->assertSame(2, FbrTaxRate::where('role', 'seller')->count());
    }

    public function test_store_validates_the_submitted_values(): void
    {
        FbrTaxRate::query()->delete();

        $this->store(['role' => 'landlord', 'value_from' => 10, 'value_to' => 5, 'filers_rate' => 120, 'non_filers_rate' => -1])
            ->assertSessionHasErrors(['role', 'value_to', 'filers_rate', 'non_filers_rate']);

        $this->assertSame(0, FbrTaxRate::count());
    }

    public function test_update_changes_both_rates_of_a_slab(): void
    {
        FbrTaxRate::query()->delete();

        [$filer, $nonFiler] = $this->pair('buyer', 0, null, 1, 2);

        $this->update([
            'slab_id' => $filer->id,
            'value_from' => 0,
            'value_to' => '',
            'filers_rate' => 5,
            'non_filers_rate' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertSame(5, (int) $filer->fresh()->rate_percent);
        $this->assertSame(10, (int) $nonFiler->fresh()->rate_percent);
    }

    public function test_update_can_move_a_slab_to_a_free_range(): void
    {
        FbrTaxRate::query()->delete();

        [$filer] = $this->pair('buyer', 0, 50_000_000, 1.25, 10.5);

        $this->update([
            'slab_id' => $filer->id,
            'value_from' => 75_000_000,
            'value_to' => 100_000_000,
            'filers_rate' => 2,
            'non_filers_rate' => 12,
        ])->assertSessionHasNoErrors();

        $this->assertSame(75_000_000, $filer->fresh()->value_from);
        $this->assertSame(100_000_000, $filer->fresh()->value_to);
        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->where('value_from', 75_000_000)->where('value_to', 100_000_000)->count());
    }

    public function test_update_rejects_a_range_that_overlaps_another_slab(): void
    {
        FbrTaxRate::query()->delete();

        [$filer] = $this->pair('buyer', 0, null, 1, 2);
        $this->pair('buyer', 40_000_000, null, 3, 4);

        $this->update([
            'slab_id' => $filer->id,
            'value_from' => 10_000_000,
            'value_to' => null,
            'filers_rate' => 5,
            'non_filers_rate' => 10,
        ])->assertSessionHasErrors('value_from');

        $this->assertSame(0, $filer->fresh()->value_from);
        $this->assertSame(4, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_update_rejects_a_non_filer_slab_reference(): void
    {
        FbrTaxRate::query()->delete();

        $this->pair('buyer', 0, null, 1, 2);
        $nonFiler = FbrTaxRate::where('role', 'buyer')->where('filer_status', 'non_filer')->firstOrFail();

        $this->update([
            'slab_id' => $nonFiler->id,
            'value_from' => 0,
            'value_to' => '',
            'filers_rate' => 5,
            'non_filers_rate' => 10,
        ])->assertSessionHasErrors('value_from');

        $this->assertSame(2, (int) $nonFiler->fresh()->rate_percent);
    }

    public function test_destroy_removes_both_rows_of_a_slab(): void
    {
        FbrTaxRate::query()->delete();

        [$filer] = $this->pair('buyer', 10_000_000, 20_000_000, 3, 6);

        $this->destroy($filer->id)->assertSessionHasNoErrors();

        $this->assertSame(0, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_destroy_blocks_the_slab_that_covers_zero(): void
    {
        FbrTaxRate::query()->delete();

        [$filer] = $this->pair('buyer', 0, null, 1, 2);

        $this->destroy($filer->id)->assertSessionHasErrors('slab_id');

        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->count());
    }

    public function test_destroy_rejects_a_non_filer_slab_reference(): void
    {
        FbrTaxRate::query()->delete();

        $this->pair('buyer', 10_000_000, 20_000_000, 3, 6);
        $nonFiler = FbrTaxRate::where('role', 'buyer')->where('filer_status', 'non_filer')->firstOrFail();

        $this->destroy($nonFiler->id)->assertSessionHasErrors('slab_id');

        $this->assertSame(2, FbrTaxRate::where('role', 'buyer')->count());
    }

    private function slab(string $role, int $from, ?int $to, float $rate, string $status = 'filer'): FbrTaxRate
    {
        return FbrTaxRate::create([
            'role' => $role,
            'filer_status' => $status,
            'value_from' => $from,
            'value_to' => $to,
            'rate_percent' => $rate,
        ]);
    }

    private function pair(string $role, int $from, ?int $to, float $filerRate, float $nonFilerRate): array
    {
        return [
            $this->slab($role, $from, $to, $filerRate, 'filer'),
            $this->slab($role, $from, $to, $nonFilerRate, 'non_filer'),
        ];
    }

    private function store(array $data)
    {
        return $this->actingAs($this->user())
            ->from(route('fbr-tax-rates.index'))
            ->post(route('fbr-tax-rates.store'), $data);
    }

    private function update(array $data)
    {
        return $this->actingAs($this->user())
            ->from(route('fbr-tax-rates.index'))
            ->post(route('fbr-tax-rates.update'), $data);
    }

    private function destroy(int $slabId)
    {
        return $this->actingAs($this->user())
            ->from(route('fbr-tax-rates.index'))
            ->post(route('fbr-tax-rates.destroy'), ['slab_id' => $slabId]);
    }

    private function user(): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test.'.uniqid().'@example.com',
            'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(),
            'gender' => 'male',
            'marital_status' => 'married',
            'permanent_address' => 'Permanent Address',
            'present_address' => 'Present Address',
            'contact' => '03001234567',
        ]);

        $user->givePermissionTo('listing_fbr_tax_rate');

        return $user;
    }
}
