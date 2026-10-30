<?php

namespace App\Services;

use App\Helpers\GetArea;
use App\Models\Block;
use App\Models\BlockPaymentPlan;
use App\Models\BlockPriceRange;
use App\Models\InstallmentTemplate;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Single source of truth for finding the properties that match a block's
 * category + construction status + size range, and for applying block-level
 * payment plans / listings to those properties.
 */
class PropertyRangeService
{
    private const DEFAULT_MARLA_SQFT = 272.25;

    private const KANAL_IN_MARLA = 20;

    /**
     * Parses a size-range string such as "5-10", "1-plus", or "1-2-kanal"
     * into [min, max] marla. Kanal values are converted to marla (1 kanal = 20).
     *
     * @return array{0: float|null, 1: float|null}
     */
    public function parseSizeRange(?string $range): array
    {
        if ($range === null || $range === '') {
            return [null, null];
        }

        $parts = explode('-', $range);
        $isKanal = in_array('kanal', $parts);
        $isPlus = in_array('plus', $parts);

        $min = (float) $parts[0];
        $max = $isPlus ? null : (float) ($parts[1] ?? $min);

        if ($isKanal) {
            $min *= self::KANAL_IN_MARLA;
            $max = $max !== null ? $max * self::KANAL_IN_MARLA : null;
        }

        return [$min, $max];
    }

    /**
     * Area of a property/unit in marla using the owning society's marla size.
     */
    public function areaInMarla($model): float
    {
        $property = $model instanceof PropertyUnit ? $model->floor?->property : $model;
        $sqft = GetArea::calculate($model->dimensions);
        $marlaSize = (float) ($property?->block?->society?->marla_size) ?: self::DEFAULT_MARLA_SQFT;

        return $sqft / $marlaSize;
    }

    /**
     * Whether a property/unit's area falls inside the given marla bounds.
     * A null bound means unbounded on that side.
     */
    public function areaInRange($model, ?float $min, ?float $max): bool
    {
        $marla = $this->areaInMarla($model);

        return ($min === null || $marla >= $min) && ($max === null || $marla <= $max);
    }

    /**
     * Computes the listing price (whole currency) for a given rate per area unit.
     */
    public function calculatedPrice($model, float $rate, string $priceUnit): int
    {
        $property = $model instanceof PropertyUnit ? $model->floor?->property : $model;
        $sqft = GetArea::calculate($model->dimensions);
        $marlaSize = (float) ($property?->block?->society?->marla_size) ?: self::DEFAULT_MARLA_SQFT;

        $area = $priceUnit === 'marla' ? $sqft / $marlaSize : $sqft / 9;

        return (int) round($rate * $area);
    }

    /**
     * Finds owner-less properties in a block matching an optional size-range string.
     */
    public function findMatchingProperties(int $blockId, ?bool $isCommercial, ?bool $isConstructed, ?string $sizeRange): Collection
    {
        [$min, $max] = $this->parseSizeRange($sizeRange);

        return $this->findMatchingPropertiesInRange($blockId, $isCommercial, $isConstructed, $min, $max);
    }

    /**
     * Finds owner-less properties in a block matching the exact category,
     * construction status and marla bounds (null = not filtered).
     */
    public function findMatchingPropertiesInRange(int $blockId, ?bool $isCommercial, ?bool $isConstructed, ?float $minSize, ?float $maxSize): Collection
    {
        $query = Property::where('block_id', $blockId)->whereNull('owner_id');

        if ($isCommercial !== null) {
            $query->whereIn('category', $isCommercial ? ['commercial'] : ['residential', 'other']);
        }

        if ($isConstructed !== null) {
            $query->whereIn('construction_status', $isConstructed ? ['constructed'] : ['in_progress', 'pending']);
        }

        $properties = $query->with(['dimensions', 'block.society'])->get();

        if ($minSize !== null || $maxSize !== null) {
            $properties = $properties->filter(fn ($property) => $this->areaInRange($property, $minSize, $maxSize));
        }

        return $properties->values();
    }

    /**
     * Owner-less units directly under the given properties.
     */
    public function findMatchingUnits(Collection $properties): Collection
    {
        $propertyIds = $properties->pluck('id')->all();

        if (empty($propertyIds)) {
            return collect();
        }

        return PropertyUnit::whereHas('floor', fn ($q) => $q->whereIn('property_id', $propertyIds))
            ->whereNull('owner_id')
            ->get();
    }

    /**
     * Applies a block-level payment plan to every matching property (and to
     * each owner-less unit when the plan is set up to apply to units) and
     * marks each affected model with is_block_payment_plan_applied = true.
     *
     * @return int Number of property/unit records that received the plan.
     */
    public function applyBlockPaymentPlan(Block $block, BlockPaymentPlan $blockPlan): int
    {
        $properties = $this->findMatchingPropertiesInRange(
            $block->id,
            $blockPlan->is_commercial,
            $blockPlan->is_constructed,
            $blockPlan->min_size,
            $blockPlan->max_size
        );
        logger($properties);
        $count = 0;

        foreach ($properties as $property) {
            $this->applyPaymentPlanToModel($property, $blockPlan);
            $count++;
        }

        if ($blockPlan->apply_to_units) {
            foreach ($this->findMatchingUnits($properties) as $unit) {
                $this->applyPaymentPlanToModel($unit, $blockPlan);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Applies a block-level listing to every matching property (optionally
     * restricted to the category + construction status collected by the
     * listing modal) and marks each affected property with
     * is_block_payment_plan_applied = true.
     *
     * @return int Number of properties that received the listing.
     */
    public function applyListing(Block $block, Request $request): int
    {
        $properties = $this->findMatchingProperties(
            $block->id,
            $this->listingCategoryFlag($request->input('category')),
            $this->listingConstructionFlag($request->input('construction_status')),
            $request->input('size_range')
        );

        $listingData = [
            'listing_type' => $request->input('listing_type'),
            'show_price' => $request->boolean('show_price'),
            'contact_no' => $request->input('contact_no'),
            'show_contact_no' => $request->boolean('show_contact_no'),
            'email' => $request->input('email'),
            'description' => $request->input('description'),
            'closed' => false,
        ];

        $count = 0;

        foreach ($properties as $property) {
            if ($request->input('listing_type') === 'sell') {
                $listingPayload = array_merge($listingData, [
                    'price' => $this->calculatedPrice(
                        $property,
                        (float) $request->input('price'),
                        $request->input('price_unit')
                    ),
                    'unit' => $request->input('price_unit'),
                ]);
            } else {
                $listingPayload = array_merge($listingData, ['price' => 0, 'unit' => 'PKR']);
            }

            $property->listing()->updateOrCreate([], $listingPayload);
            $property->update(['is_block_payment_plan_applied' => true]);
            $count++;
        }

        return $count;
    }

    /**
     * Recomputes the block flag for several properties (and, optionally, the
     * owner-less units under them) after a manual plan/listing change.
     */
    public function syncBlockPaymentPlanFlagForProperties(iterable $properties, bool $includeUnits): void
    {
        $collection = collect($properties);

        foreach ($collection as $property) {
            $this->syncBlockPaymentPlanFlag($property);
        }

        if ($includeUnits) {
            foreach ($this->findMatchingUnits($collection) as $unit) {
                $this->syncBlockPaymentPlanFlag($unit);
            }
        }
    }

    /**
     * Maps the listing modal's category value to a commercial flag
     * (null = no category restriction).
     */
    private function listingCategoryFlag(?string $category): ?bool
    {
        return match ($category) {
            'commercial' => true,
            'non_commercial' => false,
            default => null,
        };
    }

    /**
     * Maps the listing modal's construction-status value to a constructed flag
     * (null = no construction restriction).
     */
    private function listingConstructionFlag(?string $constructionStatus): ?bool
    {
        return match ($constructionStatus) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * Recomputes whether a property/unit is currently following the applicable
     * block-level configuration, i.e. its current payment plan AND listing
     * still match the block plan for its category + construction status.
     * Updates and returns the is_block_payment_plan_applied flag.
     */
    public function syncBlockPaymentPlanFlag($model): bool
    {
        $property = $model instanceof PropertyUnit ? $model->floor?->property : $model;
        $block = $property?->block;

        if (! $block || ! $property) {
            $model->update(['is_block_payment_plan_applied' => false]);

            return false;
        }

        $isCommercial = $property->category === 'commercial';
        $isConstructed = $property->construction_status === 'constructed';

        $blockPlan = $block->blockPaymentPlans()
            ->where('is_commercial', $isCommercial)
            ->where('is_constructed', $isConstructed)
            ->first();

        $planMatches = $this->paymentPlanMatchesBlockPlan($model, $blockPlan);
        $listingMatches = $this->listingMatchesBlockConfig($model, $block);

        $flag = $planMatches && $listingMatches;

        $model->update(['is_block_payment_plan_applied' => $flag]);

        return $flag;
    }

    /**
     * True when the model carries no payment plan and the block has none
     * configured, or when the model's plan exactly equals the block plan
     * (payment template + installment templates).
     */
    private function paymentPlanMatchesBlockPlan($model, ?BlockPaymentPlan $blockPlan): bool
    {
        $plan = $model->paymentPlan;

        if (! $blockPlan) {
            return $plan === null;
        }

        if (! $plan) {
            return $blockPlan->payment_template_id === null && empty($blockPlan->installment_template_ids);
        }

        if ($this->nullableValuesDiffer($plan->payment_template_id, $blockPlan->payment_template_id)) {
            return false;
        }

        return $this->installmentIdsMatch($plan, $blockPlan->installment_template_ids ?? []);
    }

    private function installmentIdsMatch($plan, array $blockInstallmentIds): bool
    {
        $planIds = $plan->installmentAttachments
            ->pluck('installment_template_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        $blockIds = collect($blockInstallmentIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values();

        return $planIds->all() === $blockIds->all();
    }

    private function nullableValuesDiffer($a, $b): bool
    {
        $a = $a === null || $a === '' ? null : (int) $a;
        $b = $b === null || $b === '' ? null : (int) $b;

        return $a !== $b;
    }

    /**
     * True when the model's current listing is still the one produced by a
     * block-level listing apply: rent listings always use price 0 / PKR, while
     * sell listings must equal the persisted block price range rate × available
     * area. A model without a listing cannot contradict the block config.
     */
    private function listingMatchesBlockConfig($model, Block $block): bool
    {
        $listing = $model->listing;

        if (! $listing) {
            return true;
        }

        if ($listing->listing_type === 'rent') {
            return (float) $listing->price === 0.0 && $listing->unit === 'PKR';
        }

        $marla = $this->areaInMarla($model);
        $range = BlockPriceRange::where('block_id', $block->id)
            ->where('price_unit', $listing->unit)
            ->get()
            ->first(fn ($r) => ($r->min_size === null || $marla >= (float) $r->min_size)
              && ($r->max_size === null || $marla <= (float) $r->max_size));

        if (! $range) {
            return false;
        }

        return (int) $listing->price === $this->calculatedPrice($model, (float) $range->price, $listing->unit);
    }

    /**
     * Applies a block-level template plan to the given property/unit: an
     * existing plan is kept and re-pointed at the newly selected template,
     * otherwise a fresh plan is created — carrying only the selected template
     * id + snapshotted listing price/unit. Every other charge value stays
     * null/false because those values are supplied by the selected payment
     * template (and installment templates). The chosen installment templates
     * are attached (syncing detaches any belonging to a previous template).
     */
    private function applyPaymentPlanToModel($model, BlockPaymentPlan $blockPlan): void
    {
        $values = array_merge([
            'payment_template_id' => $blockPlan->payment_template_id,
        ], $this->listedPriceValues($model), $this->emptyChargeValues());

        $plan = $model->paymentPlan()->first();

        if ($plan) {
            // Re-point the existing plan at the newly applied template and
            // reset any values that belonged to the previous template instead
            // of creating a duplicate plan.
            $plan->update($values);
        } else {
            $plan = $model->paymentPlan()->create(array_merge([
                'uuid' => Str::uuid(),
            ], $values));
        }

        // Attach the selected installment templates. sync() detaches every old
        // attachment so only the newly applied ones remain linked to the plan.
        // A customized attachment - one that was detached from its template by
        // an edit - belongs to the previous configuration and must not survive
        // the re-apply either, so it is dropped before the new ones are synced.
        $plan->installmentAttachments()
            ->whereNull('installment_template_id')
            ->delete();

        $plan->installmentTemplates()->sync($this->installmentSyncData($blockPlan->installment_template_ids));

        $model->update(['is_block_payment_plan_applied' => true]);
    }

    /**
     * Charge/premium snapshot columns that physically exist on the
     * property_payment_plans table, reset to empty so the selected payment
     * template keeps supplying the values. The installment-specific columns
     * (balloon_*, number_of_installments, additional_installment_amount*,
     * installment_frequency) were moved to the property_installment_plans
     * pivot and must never be written to the plan row.
     */
    private function emptyChargeValues(): array
    {
        return [
            'booking_fee' => null,
            'booking_fee_in_per' => false,
            'development_fee' => null,
            'development_fee_in_per' => false,
            'development_fee_surplus' => false,
            'down_payment' => null,
            'down_payment_in_per' => false,
            'confirmation_fee' => null,
            'confirmation_fee_in_per' => false,
            'possession_fee' => null,
            'possession_fee_in_per' => false,
            'possession_fee_surplus' => false,
            'corner_premium' => null,
            'corner_premium_in_per' => true,
            'park_facing_premium' => null,
            'park_facing_premium_in_per' => true,
            'main_boulevard_premium' => null,
            'main_boulevard_premium_in_per' => true,
        ];
    }

    /**
     * Listing price/unit for a plan: snapshotted from the property/unit's
     * current listing. Left empty when the model is not listed — no fake
     * listing data is fabricated.
     */
    private function listedPriceValues($model): array
    {
        $listing = $model->listing;

        if (! $listing) {
            return [
                'listed_price' => null,
                'listed_unit' => null,
            ];
        }

        return [
            'listed_price' => $listing->price,
            'listed_unit' => $listing->unit ?? 'PKR',
        ];
    }

    /**
     * Builds the installment-template sync payload, snapshotting each
     * template's values onto the pivot row.
     */
    private function installmentSyncData(array $installmentTemplateIds): array
    {
        $syncData = [];
        $templates = InstallmentTemplate::whereIn('id', $installmentTemplateIds)->get()->keyBy('id');

        foreach ($installmentTemplateIds as $id) {
            $tpl = $templates->get($id);
            if (! $tpl) {
                continue;
            }

            $syncData[$id] = [
                'name' => $tpl->name,
                'number_of_installments' => $tpl->number_of_installments,
                'installment_frequency' => $tpl->installment_frequency,
                'additional_installment_amount' => $tpl->additional_installment_amount,
                'additional_installment_amount_in_per' => $tpl->additional_installment_amount_in_per,
                'balloon_payment' => $tpl->balloon_payment,
                'balloon_payment_in_per' => $tpl->balloon_payment_in_per,
                'balloon_payment_frequency' => $tpl->balloon_payment_frequency,
            ];
        }

        return $syncData;
    }
}
