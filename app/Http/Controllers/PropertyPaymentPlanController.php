<?php

namespace App\Http\Controllers;

use App\Helpers\GetArea;
use App\Models\Block;
use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\Property;
use App\Models\PropertyPaymentPlan;
use App\Models\PropertyPaymentPlanInstallmentTemplate;
use App\Models\PropertyUnit;
use App\Support\InstallmentPlanCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyPaymentPlanController extends Controller
{
    private const DEFAULT_MARLA_SQFT = 272.25;

    private const KANAL_IN_MARLA = 20;

    private const FEE_KEYS = [
        'booking_fee',
        'development_fee',
        'down_payment',
        'transfer_fee',
        'confirmation_fee',
        'possession_fee',
    ];

    private const SURPLUS_KEYS = ['development_fee', 'transfer_fee', 'possession_fee'];

    private const PREMIUM_KEYS = ['corner_premium', 'park_facing_premium', 'main_boulevard_premium'];

    public function store(Request $request)
    {
        dd($request->all());
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
            'planable_type' => 'required|in:society,block,property',
            'payment_template_id' => 'nullable|exists:payment_templates,id',
            'installment_template_ids' => 'nullable|array',
            'installment_template_ids.*' => 'exists:installment_templates,id',
            'size_range' => 'nullable|string',
            'apply_to_units' => 'nullable|boolean',
            'is_commercial' => 'nullable|boolean',
        ]);

        if (! $request->payment_template_id && empty($request->installment_template_ids)) {
            return back()->with('error', 'Please select at least one payment or installment template.');
        }

        // Ids may arrive as separate entries or comma-joined strings
        // (e.g. societies page sends "uuid1,uuid2" in a single input).
        $ids = collect($request->ids)
            ->flatMap(fn ($id) => explode(',', $id))
            ->map(fn ($id) => trim($id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        try {
            $result = $this->getEligibleProperties(
                $ids,
                $request->planable_type,
                $request->size_range,
                $request->boolean('is_commercial')
            );

            if (empty($result['applied'])) {
                $parts = [];
                if (count($result['category_skipped'])) {
                    $parts[] = count($result['category_skipped']).' category mismatch';
                }
                if (count($result['size_skipped'])) {
                    $parts[] = count($result['size_skipped']).' size mismatch';
                }
                $reason = $parts ? ' ('.implode(', ', $parts).')' : '';

                return back()->with('error', 'No matching properties found'.$reason.'.');
            }

            DB::beginTransaction();

            $propertyIds = collect($result['applied'])->pluck('id')->toArray();
            $count = $this->applyPlan(
                $propertyIds,
                $request->boolean('apply_to_units'),
                $this->nullableId($request->payment_template_id),
                $request->installment_template_ids ?? []
            );

            DB::commit();

            $appliedNames = collect($result['applied'])->pluck('property_no')->filter()->implode(', ');
            $appliedCount = count($result['applied']);
            $msg = "Payment plan applied to {$count} record(s) ({$appliedCount} properties: {$appliedNames}).";

            if (count($result['category_skipped'])) {
                $skippedNames = collect($result['category_skipped'])->pluck('property_no')->filter()->implode(', ');
                $skippedCats = collect($result['category_skipped'])->pluck('category')->unique()->implode('/');
                $msg .= ' Skipped '.count($result['category_skipped'])." ({$skippedCats}): {$skippedNames}.";
            }

            if (count($result['size_skipped'])) {
                $sizeNames = collect($result['size_skipped'])->pluck('property_no')->filter()->implode(', ');
                $msg .= ' Skipped '.count($result['size_skipped'])." (size mismatch): {$sizeNames}.";
            }

            return back()->with('success', $msg);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return back()->with('error', 'An error occurred while applying the payment plan: '.$e->getMessage());
        }
    }

    /**
     * Applies the plan to every given property (and, if requested, to each
     * owner-less unit under those properties) as its own plan record.
     * Updates existing plans instead of creating duplicates.
     */
    private function applyPlan(array $propertyIds, bool $includeUnits, ?int $paymentTemplateId, array $installmentTemplateIds): int
    {
        $count = 0;

        // Apply to Properties
        $properties = Property::whereIn('id', $propertyIds)->whereNull('owner_id')->get();

        foreach ($properties as $property) {
            $count += $this->upsertPaymentPlan(
                $property,
                'App\\Models\\Property',
                $paymentTemplateId,
                $installmentTemplateIds
            );
        }

        // Apply to Property Units if requested
        if ($includeUnits) {
            $units = PropertyUnit::whereHas('floor', fn ($q) => $q->whereIn('property_id', $propertyIds))
                ->whereNull('owner_id')
                ->get();

            foreach ($units as $unit) {
                $count += $this->upsertPaymentPlan(
                    $unit,
                    'App\\Models\\PropertyUnit',
                    $paymentTemplateId,
                    $installmentTemplateIds
                );
            }
        }

        return $count;
    }

    /**
     * Creates or updates payment plan for a model using relationships.
     * If plan exists, updates it; otherwise creates new one.
     * Syncs installment templates (adds new, removes old, updates existing).
     */
    private function upsertPaymentPlan($model, string $modelClass, ?int $paymentTemplateId, array $installmentTemplateIds, array $values = []): int
    {
        // Check if plan already exists
        $existingPlan = $model->paymentPlan()->first();

        if ($existingPlan) {
            // Update existing plan
            $existingPlan->update(array_merge([
                'payment_template_id' => $paymentTemplateId,
            ], $values));
            $plan = $existingPlan;
        } else {
            // Create new plan
            $plan = $model->paymentPlan()->create(array_merge([
                'uuid' => Str::uuid(),
                'payment_template_id' => $paymentTemplateId,
            ], $values));
        }

        // Sync installment templates (add new, remove old, keep existing),
        // snapshotting each template's values onto the pivot row so the
        // source template is never mutated.
        $plan->installmentTemplates()->sync($this->installmentSyncData($installmentTemplateIds));

        return 1;
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

    /**
     * Updates a payment plan's own fee/premium values. The submitted values
     * are snapshotted onto the plan record and the payment template reference
     * is cleared so the template itself is never modified.
     */
    public function update(Request $request, PropertyPaymentPlan $paymentPlan)
    {
        $request->validate([
            'payment_template_id' => 'nullable|exists:payment_templates,id',
            'booking_fee' => ['nullable', 'numeric', 'min:0', $request->boolean('booking_fee_in_per') ? 'max:100' : ''],
            'booking_fee_in_per' => 'nullable|boolean',
            'development_fee' => ['nullable', 'numeric', 'min:0', $request->boolean('development_fee_in_per') ? 'max:100' : ''],
            'development_fee_in_per' => 'nullable|boolean',
            'development_fee_surplus' => 'nullable|boolean',
            'down_payment' => ['nullable', 'numeric', 'min:0', $request->boolean('down_payment_in_per') ? 'max:100' : ''],
            'down_payment_in_per' => 'nullable|boolean',
            'transfer_fee' => ['nullable', 'numeric', 'min:0', $request->boolean('transfer_fee_in_per') ? 'max:100' : ''],
            'transfer_fee_in_per' => 'nullable|boolean',
            'transfer_fee_surplus' => 'nullable|boolean',
            'confirmation_fee' => ['nullable', 'numeric', 'min:0', $request->boolean('confirmation_fee_in_per') ? 'max:100' : ''],
            'confirmation_fee_in_per' => 'nullable|boolean',
            'possession_fee' => ['nullable', 'numeric', 'min:0', $request->boolean('possession_fee_in_per') ? 'max:100' : ''],
            'possession_fee_in_per' => 'nullable|boolean',
            'possession_fee_surplus' => 'nullable|boolean',
            'corner_premium' => 'nullable|numeric|min:0|max:100',
            'park_facing_premium' => 'nullable|numeric|min:0|max:100',
            'main_boulevard_premium' => 'nullable|numeric|min:0|max:100',
        ], [
            'booking_fee.max' => $request->boolean('booking_fee_in_per') ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
            'development_fee.max' => $request->boolean('development_fee_in_per') ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
            'down_payment.max' => $request->boolean('down_payment_in_per') ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
            'transfer_fee.max' => $request->boolean('transfer_fee_in_per') ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
            'confirmation_fee.max' => $request->boolean('confirmation_fee_in_per') ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
            'possession_fee.max' => $request->boolean('possession_fee_in_per') ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
        ]);

        try {
            $paymentTemplateId = $this->nullableId($request->payment_template_id);
            $chargeValues = $this->chargeValuesFromRequest($request);

            if ($paymentTemplateId) {
                $template = PaymentTemplate::find($paymentTemplateId);

                if ($template && ! $this->chargesMatchTemplate($request, $template)) {
                    // A single value was changed → snapshot the submitted values
                    // onto the plan and drop the template link.
                    $paymentTemplateId = null;
                } else {
                    // The template is applied as-is → keep the template link so
                    // the template keeps supplying the values.
                    $chargeValues = $this->emptyChargeValues();
                }
            }

            $paymentPlan->update(array_merge([
                'payment_template_id' => $paymentTemplateId,
            ], $chargeValues));

            return back()->with('success', 'Payment plan values updated.');
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while updating the payment plan: '.$e->getMessage());
        }
    }

    /**
     * Detaches a single installment attachment (template-linked or custom)
     * from a payment plan.
     */
    public function detachInstallment(PropertyPaymentPlan $paymentPlan, PropertyPaymentPlanInstallmentTemplate $installmentAttachment)
    {
        try {
            $installmentAttachment->delete();

            return back()->with('success', 'Installment plan removed.');
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while removing the installment plan: '.$e->getMessage());
        }
    }

    /**
     * Applies a plan to a single property/unit from the details tab.
     * Accepts a payment template, installment templates, and/or direct
     * custom charge values so a full payment plan can be created without
     * requiring an installment plan first.
     */
    public function applySingle(Request $request)
    {
        $request->validate([
            'planable_type' => 'required|in:property,unit',
            'planable_uuid' => 'required|string',
            'payment_template_id' => 'nullable|exists:payment_templates,id',
            'installment_template_ids' => 'nullable|array',
            'installment_template_ids.*' => 'exists:installment_templates,id',
            'booking_fee' => 'nullable|numeric|min:0',
            'booking_fee_in_per' => 'nullable|boolean',
            'development_fee' => 'nullable|numeric|min:0',
            'development_fee_in_per' => 'nullable|boolean',
            'development_fee_surplus' => 'nullable|boolean',
            'down_payment' => 'nullable|numeric|min:0',
            'down_payment_in_per' => 'nullable|boolean',
            'transfer_fee' => 'nullable|numeric|min:0',
            'transfer_fee_in_per' => 'nullable|boolean',
            'transfer_fee_surplus' => 'nullable|boolean',
            'confirmation_fee' => 'nullable|numeric|min:0',
            'confirmation_fee_in_per' => 'nullable|boolean',
            'possession_fee' => 'nullable|numeric|min:0',
            'possession_fee_in_per' => 'nullable|boolean',
            'possession_fee_surplus' => 'nullable|boolean',
            'corner_premium' => 'nullable|numeric|min:0|max:100',
            'park_facing_premium' => 'nullable|numeric|min:0|max:100',
            'main_boulevard_premium' => 'nullable|numeric|min:0|max:100',
        ]);

        if (! $request->payment_template_id && empty($request->installment_template_ids) && ! $this->hasAnyCharge($request)) {
            return back()->with('error', 'Please select a template or enter at least one charge value.');
        }

        try {
            $modelClass = $request->planable_type === 'unit' ? PropertyUnit::class : Property::class;
            $model = $modelClass::where('uuid', $request->planable_uuid)->firstOrFail();

            $paymentTemplateId = $this->nullableId($request->payment_template_id);
            $chargeValues = $this->chargeValuesFromRequest($request);

            if ($paymentTemplateId) {
                $template = PaymentTemplate::find($paymentTemplateId);

                if ($template && ! $this->chargesMatchTemplate($request, $template)) {
                    // A single value was changed → snapshot the submitted values
                    // onto the plan and drop the template link.
                    $paymentTemplateId = null;
                } else {
                    // The template is applied as-is → keep the template link so
                    // the template keeps supplying the values.
                    $chargeValues = $this->emptyChargeValues();
                }
            }

            DB::transaction(function () use ($model, $modelClass, $paymentTemplateId, $request, $chargeValues) {
                $this->upsertPaymentPlan(
                    $model,
                    $modelClass,
                    $paymentTemplateId,
                    $request->installment_template_ids ?? [],
                    $chargeValues
                );
            });

            return back()->with('success', 'Payment plan applied successfully.');
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return back()->with('error', 'An error occurred while applying the payment plan: '.$e->getMessage());
        }
    }

    /**
     * Attaches installment template(s) to an existing payment plan without
     * touching the plan's payment values.
     */
    public function attachInstallment(Request $request, PropertyPaymentPlan $paymentPlan)
    {
        $request->validate([
            'installment_template_ids' => 'required|array',
            'installment_template_ids.*' => 'exists:installment_templates,id',
        ]);

        try {
            $paymentPlan->installmentTemplates()->syncWithoutDetaching($this->installmentSyncData($request->installment_template_ids));

            return back()->with('success', 'Installment plan added.');
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return back()->with('error', 'An error occurred while adding the installment plan: '.$e->getMessage());
        }
    }

    /**
     * True if any charge/premium field was submitted with a value.
     */
    private function hasAnyCharge(Request $request): bool
    {
        foreach (array_merge(self::FEE_KEYS, self::PREMIUM_KEYS) as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Snapshots the submitted charge/premium fields onto the plan. Values
     * left blank stay null so the payment template (if any) still supplies
     * them.
     */
    private function chargeValuesFromRequest(Request $request): array
    {
        $values = [];

        foreach (self::FEE_KEYS as $key) {
            $values[$key] = $this->nullableNumber($request->input($key));
            $values[$key.'_in_per'] = $request->boolean($key.'_in_per');
            if (in_array($key, self::SURPLUS_KEYS)) {
                $values[$key.'_surplus'] = $request->boolean($key.'_surplus');
            }
        }

        foreach (self::PREMIUM_KEYS as $key) {
            $values[$key] = $this->nullableNumber($request->input($key));
            $values[$key.'_in_per'] = true;
        }

        return $values;
    }

    /**
     * True when every submitted charge/premium field matches the given
     * template's values, i.e. the user applied the template as-is without
     * changing a single value.
     */
    private function chargesMatchTemplate(Request $request, PaymentTemplate $template): bool
    {
        foreach (self::FEE_KEYS as $key) {
            if (! $this->numbersEqual($request->input($key), $template->{$key})) {
                return false;
            }

            if ((bool) $request->boolean($key.'_in_per') !== (bool) ($template->{$key.'_in_per'} ?? false)) {
                return false;
            }

            if (in_array($key, self::SURPLUS_KEYS)
              && (bool) $request->boolean($key.'_surplus') !== (bool) ($template->{$key.'_surplus'} ?? false)) {
                return false;
            }
        }

        foreach (self::PREMIUM_KEYS as $key) {
            if (! $this->numbersEqual($request->input($key), $template->{$key})) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compares a submitted charge value against a template value.
     */
    private function numbersEqual($input, $templateValue): bool
    {
        $inputValue = $this->nullableNumber($input);
        $templateValue = $templateValue === null || $templateValue === '' ? null : (float) $templateValue;

        if ($inputValue === null && $templateValue === null) {
            return true;
        }

        if ($inputValue === null || $templateValue === null) {
            return false;
        }

        return abs($inputValue - $templateValue) < 0.000001;
    }

    /**
     * All charge/premium values reset to null so an unchanged template keeps
     * supplying every value.
     */
    private function emptyChargeValues(): array
    {
        $values = [];

        foreach (self::FEE_KEYS as $key) {
            $values[$key] = null;
            $values[$key.'_in_per'] = false;
            if (in_array($key, self::SURPLUS_KEYS)) {
                $values[$key.'_surplus'] = false;
            }
        }

        foreach (self::PREMIUM_KEYS as $key) {
            $values[$key] = null;
            $values[$key.'_in_per'] = true;
        }

        return $values;
    }

    /**
     * Deletes the entire payment plan (and its installment attachments).
     */
    public function destroy(PropertyPaymentPlan $paymentPlan)
    {
        try {
            $paymentPlan->delete();

            return back()->with('success', 'Payment plan removed.');
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while removing the payment plan: '.$e->getMessage());
        }
    }

    /**
     * Edits a single attached installment's values. The edited values are
     * snapshotted onto the attachment and the source template reference is
     * cleared so the template itself is never modified.
     */
    public function updateInstallment(Request $request, PropertyPaymentPlan $paymentPlan, PropertyPaymentPlanInstallmentTemplate $installmentAttachment)
    {
        $isAdditionalPercentage = $request->boolean('additional_installment_amount_in_per');
        $isBalloonPercentage = $request->boolean('balloon_payment_in_per');

        $request->validate([
            'name' => 'nullable|string|max:100',
            'number_of_installments' => 'required|integer|min:1|max:360',
            'installment_frequency' => 'required|in:monthly,quarterly,biannually,annually',
            'additional_installment_amount' => ['required', 'numeric', 'min:0', 'max:1000000', $isAdditionalPercentage ? 'max:100' : ''],
            'additional_installment_amount_in_per' => 'nullable|boolean',
            'balloon_payment' => ['nullable', 'numeric', 'min:0', 'max:1000000', $isBalloonPercentage ? 'max:100' : ''],
            'balloon_payment_in_per' => 'nullable|boolean',
            'balloon_payment_frequency' => 'nullable|in:quarterly,biannually,annually',
        ], [
            'additional_installment_amount.max' => $isAdditionalPercentage ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
            'balloon_payment.max' => $isBalloonPercentage ? 'Percentage must be between 0-100' : 'Amount cannot exceed 1,000,000',
        ]);

        if ($request->filled('balloon_payment') && ! $request->filled('balloon_payment_frequency')) {
            return back()->withErrors(['balloon_payment_frequency' => 'Balloon frequency is required when balloon payment is set'])->withInput();
        }

        if ($request->filled('balloon_payment') && $request->filled('balloon_payment_frequency')) {
            if (! InstallmentPlanCalculator::balloonFrequencyValid($request->input('installment_frequency'), $request->input('balloon_payment_frequency'))) {
                return back()->withErrors(['balloon_payment_frequency' => 'Balloon frequency must be less frequent than the installment frequency (e.g. quarterly balloons with monthly installments)'])->withInput();
            }

            $ipp = InstallmentPlanCalculator::installmentsPerYear($request->input('installment_frequency'));
            if ((int) $request->input('number_of_installments') % $ipp !== 0) {
                return back()->withErrors(['number_of_installments' => 'With a balloon payment the number of installments must be a whole multiple of '.$ipp.' ('.ucfirst($request->input('installment_frequency')).')'])->withInput();
            }
        }

        try {
            $installmentAttachment->update([
                'installment_template_id' => null,
                'name' => $request->input('name') ?: ($installmentAttachment->name ?: $installmentAttachment->installmentTemplate?->name),
                'number_of_installments' => $request->input('number_of_installments'),
                'installment_frequency' => $request->input('installment_frequency'),
                'additional_installment_amount' => $request->input('additional_installment_amount'),
                'additional_installment_amount_in_per' => $request->boolean('additional_installment_amount_in_per'),
                'balloon_payment' => $this->nullableNumber($request->input('balloon_payment')),
                'balloon_payment_in_per' => $request->boolean('balloon_payment_in_per'),
                'balloon_payment_frequency' => $request->input('balloon_payment_frequency'),
            ]);

            return back()->with('success', 'Installment plan updated.');
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while updating the installment plan: '.$e->getMessage());
        }
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Classifies each property UUID into applied / category-skipped / size-skipped.
     *
     * When $isCommercial is true → only commercial properties match.
     * When $isCommercial is false → residential + other properties match (all except commercial).
     *
     * @param  array  $ids  Array of property UUIDs, or society/block identifiers.
     * @param  string  $planableType  'society', 'block' or 'property'.
     * @param  string|null  $sizeRange  e.g. '0-5-marla'.
     * @param  bool  $isCommercial  true = commercial only, false = non-commercial.
     * @return array{applied: array, category_skipped: array, size_skipped: array}
     */
    private function getEligibleProperties(array $ids, string $planableType, ?string $sizeRange, bool $isCommercial): array
    {
        $query = match ($planableType) {
            'society' => Property::whereHas('block.society', fn ($q) => $q->whereIn('uuid', $ids)),
            'block' => Property::whereHas('block', fn ($q) => $q->whereIn('id', $ids)),
            default => Property::whereIn('uuid', $ids),
        };

        $query->whereNull('owner_id');

        $properties = $query->with('dimensions', 'block.society')->get();

        $applied = [];
        $categorySkipped = [];
        $sizeSkipped = [];

        foreach ($properties as $property) {
            $propIsCommercial = $property->category === 'commercial';
            if ($isCommercial !== $propIsCommercial) {
                $categorySkipped[] = $property;

                continue;
            }

            if ($sizeRange) {
                [$min, $max] = $this->parseSizeRange($sizeRange);
                if (! $this->areaInRange($property, $min, $max)) {
                    $sizeSkipped[] = $property;

                    continue;
                }
            }

            $applied[] = $property;
        }

        return [
            'applied' => $applied,
            'category_skipped' => $categorySkipped,
            'size_skipped' => $sizeSkipped,
        ];
    }

    private function areaInRange(Property $property, float $min, ?float $max): bool
    {
        $sqft = GetArea::calculate($property->dimensions);
        $marlaSize = $property->block->society->marla_size ?: self::DEFAULT_MARLA_SQFT;

        $marla = $sqft / $marlaSize;

        return $marla > $min && ($max === null || $marla <= $max);
    }

    private function parseSizeRange(string $range): array
    {
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
     * Print view for a property/unit payment plan. Supports:
     *   - ?mode=simple  existing full document (default), filtered to ?plans
     *   - ?mode=brief   compact bank-receipt schedule layout (max 3 plans per row)
     *   - ?plans=uuid1,uuid2  installment attachment uuids to include
     *   - ?applicant=uuid     optional applicant personalisation for simple print
     */
    public function printPlan(Request $request, string $uuid)
    {
        $property = Property::where('uuid', $uuid)->first()
          ?? PropertyUnit::where('uuid', $uuid)->first();

        abort_unless($property, 404);

        $listing = $property->listing;
        $plan = $property->paymentPlan;

        $mode = in_array($request->query('mode'), ['simple', 'brief'], true) ? $request->query('mode') : 'simple';
        $includeInstallments = filter_var($request->query('include_installments', true), FILTER_VALIDATE_BOOL);
        $selectedUuids = $includeInstallments && $request->query('plans')
          ? array_values(array_filter(array_map('trim', explode(',', (string) $request->query('plans')))))
          : [];

        $applicants = $listing?->inquiries()->with('visitor')->orderByDesc('created_at')->get() ?? collect();

        $selectedApplicant = $request->query('applicant')
          ? $applicants->firstWhere('uuid', $request->query('applicant'))
          : null;

        $user = auth()->user();
        $fullName = trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));
        $printedByName = $fullName !== '' ? $fullName : null;
        $printedByRole = $user?->getRoleNames()->join(', ') ?: null;

        return view('components.property.payment-plans-print', compact(
            'property', 'listing', 'plan', 'mode', 'includeInstallments', 'selectedUuids', 'applicants',
            'selectedApplicant', 'printedByName', 'printedByRole'
        ));
    }

    public function printPlans(Request $request)
    {
        $request->validate([
            'block_id' => 'required|exists:blocks,id',
            'plan_ids' => 'required|array',
            'plan_ids.*' => 'exists:payment_templates,id',
        ]);

        $block = Block::find($request->block_id);
        $properties_with_templates = $block->properties()
            ->whereHas('paymentPlan', function ($query) use ($request) {
                $query->whereIn('payment_template_id', $request->plan_ids);
            })->get();

        dd($properties_with_templates);
    }
}
