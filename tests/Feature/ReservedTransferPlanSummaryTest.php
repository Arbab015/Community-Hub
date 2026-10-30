<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\Society;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The plan summary of a transfer-related reserved plan must bill the transfer
 * fee only: gross = final = fee. A frozen zero fee is still shown as 0; it must
 * never fall back to the listed price as the gross amount.
 */
class ReservedTransferPlanSummaryTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;

    private Property $property;

    private PropertyOwner $ownership;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'first_name' => 'Transfer', 'last_name' => 'Owner',
            'email' => 'transfer.'.uniqid().'@example.com', 'password' => 'password',
            'cnic_passport' => 'CNIC-'.uniqid(), 'gender' => 'male', 'marital_status' => 'married',
            'permanent_address' => 'P', 'present_address' => 'P', 'contact' => '03001234567',
        ]);

        $society = Society::create([
            'name' => 'Summary Society', 'address' => 'Address', 'city' => 'Lahore',
            'country' => 'Pakistan', 'owner_id' => $this->owner->id,
        ]);

        $block = Block::create(['society_id' => $society->id, 'name' => 'Block A']);

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
            'started_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner);
    }

    public function test_a_frozen_zero_transfer_fee_shows_zero_gross_and_final_not_the_listed_price(): void
    {
        $this->makePlan('reservation', 0);

        $html = $this->renderSummary();

        $this->assertStringContainsString('id="summary_transfer_fee"', $html);
        $this->assertStringContainsString('0% of listed price', $html);
        $this->assertSame('0', $this->summaryValue($html, 'summary_gross'));
        $this->assertSame('0', $this->summaryValue($html, 'summary_final_amount'));
    }

    public function test_a_transfer_plan_shows_the_fee_as_gross_and_final_with_its_percent(): void
    {
        $this->makePlan('transfer', 60_000);

        $html = $this->renderSummary();

        $this->assertStringContainsString('15% of listed price', $html);
        $this->assertSame('60,000', $this->summaryValue($html, 'summary_gross'));
        $this->assertSame('60,000', $this->summaryValue($html, 'summary_final_amount'));
    }

    private function makePlan(string $planType, float $transferFee): PropertyReservedPaymentPlan
    {
        return PropertyReservedPaymentPlan::create([
            'property_ownerable_type' => Property::class,
            'property_ownerable_id' => $this->property->id,
            'property_owner_id' => $this->ownership->id,
            'plan_type' => $planType,
            'listed_price' => 400_000,
            'transfer_fee' => $transferFee,
            'transfer_fee_in_per' => false,
            'final_price' => $transferFee,
            'price_unit' => 'PKR',
            'with_installment' => false,
            'status' => 'completed',
        ]);
    }

    private function renderSummary(): string
    {
        return view('components.property.reserved-payment-plans', ['property' => $this->property])->render();
    }

    private function summaryValue(string $html, string $id): string
    {
        $marker = 'id="'.$id.'"';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, $id.' not found');

        $after = substr($html, $start);
        $close = strpos($after, '>');
        $end = strpos($after, '</span>');

        return trim(strip_tags(substr($after, $close + 1, $end - $close - 1)));
    }
}
