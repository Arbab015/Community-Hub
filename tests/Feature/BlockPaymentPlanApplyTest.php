<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockPaymentPlan;
use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\Society;
use App\Models\User;
use App\Services\PropertyRangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The project's migration chain cannot run against a truly fresh database
 * (see 2026_08_04_220445 which drops property_units.no that the create
 * migration comments out), so these tests wrap the existing migrated schema
 * in a per-test transaction that is always rolled back.
 */
class BlockPaymentPlanApplyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_block_level_apply_creates_plan_with_only_template_and_listing_snapshot_when_property_has_no_existing_plan(): void
    {
        $user = $this->user();
        $block = $this->block();
        $property = $this->makeProperty($block);
        $this->listing($property, 400000, 'PKR');
        $template = $this->paymentTemplate($user);

        app(PropertyRangeService::class)->applyBlockPaymentPlan($block, $this->blockPlan($block, $template->id));

        $plan = $property->fresh()->paymentPlan;

        $this->assertNotNull($plan);
        $this->assertSame($template->id, $plan->payment_template_id);
        $this->assertSame($property->id, $plan->planable_id);
        $this->assertSame(Property::class, $plan->planable_type);

        // Listing details are snapshotted only because the property is listed.
        $this->assertEquals(400000, $plan->listed_price);
        $this->assertSame('PKR', $plan->listed_unit);

        // Payment-template charge fields must stay empty at creation.
        $this->assertNull($plan->booking_fee);
        $this->assertNull($plan->development_fee);
        $this->assertNull($plan->down_payment);
        $this->assertNull($plan->confirmation_fee);
        $this->assertNull($plan->possession_fee);
        $this->assertNull($plan->corner_premium);
        $this->assertNull($plan->park_facing_premium);
        $this->assertNull($plan->main_boulevard_premium);

        // Columns removed from the table must never appear in the insert payload.
        $attributes = $plan->getAttributes();
        $this->assertArrayNotHasKey('balloon_payment', $attributes);
        $this->assertArrayNotHasKey('balloon_payment_in_per', $attributes);
        $this->assertArrayNotHasKey('balloon_payment_frequency', $attributes);
        $this->assertArrayNotHasKey('number_of_installments', $attributes);
        $this->assertArrayNotHasKey('additional_installment_amount', $attributes);
        $this->assertArrayNotHasKey('additional_installment_amount_in_per', $attributes);
        $this->assertArrayNotHasKey('installment_frequency', $attributes);

        $this->assertTrue((bool) $property->fresh()->is_block_payment_plan_applied);
    }

    public function test_block_level_apply_without_listing_does_not_fabricate_listing_or_charge_values(): void
    {
        $user = $this->user();
        $block = $this->block();
        $property = $this->makeProperty($block);
        $template = $this->paymentTemplate($user);

        app(PropertyRangeService::class)->applyBlockPaymentPlan($block, $this->blockPlan($block, $template->id));

        $plan = $property->fresh()->paymentPlan;

        $this->assertNotNull($plan);
        $this->assertSame($template->id, $plan->payment_template_id);
        $this->assertNull($plan->listed_price);
        $this->assertNull($plan->listed_unit);
        $this->assertNull($plan->booking_fee);
        $this->assertNull($plan->down_payment);
        $this->assertArrayNotHasKey('balloon_payment', $plan->getAttributes());
    }

    public function test_block_level_apply_updates_existing_plan_rather_than_duplicating_it_and_swaps_installment_templates(): void
    {
        $user = $this->user();
        $block = $this->block();
        $property = $this->makeProperty($block);
        $oldTemplate = $this->paymentTemplate($user);
        $newTemplate = $this->paymentTemplate($user);
        $oldInstallment = $this->installmentTemplate($user, 'Old Installment');
        $newInstallment = $this->installmentTemplate($user, 'New Installment');

        // The property already carries a plan from the old template, with
        // snapshotted charges and an old installment attachment.
        $plan = $property->paymentPlan()->create([
            'payment_template_id' => $oldTemplate->id,
            'booking_fee' => 12345,
            'down_payment' => 999,
            'listed_price' => 111,
            'listed_unit' => 'PKR',
        ]);
        $plan->installmentTemplates()->sync([$oldInstallment->id => ['name' => 'Old Installment']]);

        // Apply a block-level payment plan with the new template + installment.
        app(PropertyRangeService::class)->applyBlockPaymentPlan($block, $this->blockPlan($block, $newTemplate->id, [$newInstallment->id]));

        $property->refresh();
        $this->assertSame(1, $property->paymentPlans()->count());

        $plan = $property->paymentPlan;
        $this->assertSame($newTemplate->id, $plan->payment_template_id);

        // Values belonging to the previous template are reset.
        $this->assertNull($plan->booking_fee);
        $this->assertNull($plan->down_payment);

        // The old installment is detached and the new one is linked with its snapshot.
        $attachments = $plan->installmentAttachments()->get();
        $this->assertCount(1, $attachments);
        $this->assertSame($newInstallment->id, (int) $attachments->first()->installment_template_id);
        $this->assertSame('New Installment', $attachments->first()->name);
    }

    public function test_block_level_apply_without_installments_removes_old_installment_attachments(): void
    {
        $user = $this->user();
        $block = $this->block();
        $property = $this->makeProperty($block);
        $oldTemplate = $this->paymentTemplate($user);
        $newTemplate = $this->paymentTemplate($user);
        $oldInstallment = $this->installmentTemplate($user, 'Old Installment');

        $plan = $property->paymentPlan()->create([
            'payment_template_id' => $oldTemplate->id,
            'booking_fee' => 12345,
        ]);
        $plan->installmentTemplates()->sync([$oldInstallment->id => ['name' => 'Old Installment']]);

        // New block plan references a template but selects no installment template.
        app(PropertyRangeService::class)->applyBlockPaymentPlan($block, $this->blockPlan($block, $newTemplate->id, []));

        $property->refresh();
        $this->assertSame(1, $property->paymentPlans()->count());

        $plan = $property->paymentPlan;
        $this->assertSame($newTemplate->id, $plan->payment_template_id);
        $this->assertCount(0, $plan->installmentAttachments()->get());
    }

    public function test_block_level_apply_links_payment_plan_to_property_and_marks_block_plan_applied(): void
    {
        $user = $this->user();
        $block = $this->block();
        $property = $this->makeProperty($block);
        $template = $this->paymentTemplate($user);
        $installment = $this->installmentTemplate($user, 'Installment A');

        app(PropertyRangeService::class)->applyBlockPaymentPlan($block, $this->blockPlan($block, $template->id, [$installment->id]));

        $this->assertTrue((bool) $property->fresh()->is_block_payment_plan_applied);
        $attachments = $property->fresh()->paymentPlan->installmentAttachments()->get();
        $this->assertCount(1, $attachments);
        $this->assertSame($installment->id, (int) $attachments->first()->installment_template_id);
    }

    private function user(): User
    {
        return User::create([
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
    }

    private function block(): Block
    {
        $owner = $this->user();

        $society = Society::create([
            'name' => 'Test Society',
            'address' => 'Address',
            'city' => 'Lahore',
            'country' => 'Pakistan',
            'owner_id' => $owner->id,
        ]);

        return Block::create([
            'society_id' => $society->id,
            'name' => 'Block A',
        ]);
    }

    private function makeProperty(Block $block): Property
    {
        return Property::create([
            'block_id' => $block->id,
            'property_no' => 'P-'.uniqid(),
            'type' => 'residential',
            'category' => 'residential',
            'construction_status' => 'constructed',
            'street' => 'Street 1',
        ]);
    }

    private function listing(Property $property, int $price, string $unit): PropertyListing
    {
        return $property->listing()->create([
            'contact_no' => '03001234567',
            'email' => 'test@example.com',
            'listing_type' => 'sell',
            'price' => $price,
            'unit' => $unit,
            'description' => 'Test listing',
        ]);
    }

    private function paymentTemplate(User $user): PaymentTemplate
    {
        return PaymentTemplate::create([
            'owner_id' => $user->id,
            'name' => 'Payment Template '.uniqid(),
            'booking_fee' => 50000,
            'booking_fee_in_per' => false,
            'development_fee' => 100000,
            'development_fee_in_per' => false,
            'down_payment' => 10,
            'down_payment_in_per' => true,
        ]);
    }

    private function installmentTemplate(User $user, string $name): InstallmentTemplate
    {
        return InstallmentTemplate::create([
            'owner_id' => $user->id,
            'name' => $name,
            'number_of_installments' => 12,
            'additional_installment_amount' => 5,
            'additional_installment_amount_in_per' => true,
            'installment_frequency' => 'monthly',
        ]);
    }

    private function blockPlan(Block $block, ?int $paymentTemplateId, array $installmentTemplateIds = []): BlockPaymentPlan
    {
        return new BlockPaymentPlan([
            'block_id' => $block->id,
            'is_commercial' => false,
            'is_constructed' => true,
            'payment_template_id' => $paymentTemplateId,
            'installment_template_ids' => $installmentTemplateIds,
            'apply_to_units' => false,
            'min_size' => null,
            'max_size' => null,
        ]);
    }
}
