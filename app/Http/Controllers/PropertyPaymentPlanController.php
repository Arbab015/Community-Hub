<?php

namespace App\Http\Controllers;

use App\Helpers\GetArea;
use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\PropertyPaymentPlan;
use App\Models\PropertyPaymentPlanInstallmentTemplate;
use App\Models\PropertyUnit;
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
        $request->validate([
            'ids' => 'required|string',
            'planable_type' => 'required|in:society,property',
            'payment_template_id' => 'nullable|exists:payment_templates,id',
            'installment_template_ids' => 'nullable|array',
            'installment_template_ids.*' => 'exists:installment_templates,id',
            'size_range' => 'nullable|string',
            'apply_to_units' => 'nullable|boolean',
        ]);

        if (! $request->payment_template_id && empty($request->installment_template_ids)) {
            return back()->with('error', 'Please select at least one payment or installment template.');
        }

        try {
            $propertyIds = $this->getEligiblePropertyIds(
                explode(',', $request->ids),
                $request->planable_type,
                $request->size_range
            );

            if (empty($propertyIds)) {
                return back()->with('error', 'No matching properties found.');
            }

            DB::beginTransaction();

            $count = $this->applyPlan(
                $propertyIds,
                $request->boolean('apply_to_units'),
                $this->nullableId($request->payment_template_id),
                $request->installment_template_ids ?? []
            );

            DB::commit();

            return back()->with('success', "Payment plan applied successfully to {$count} record(s).");
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
        $existingPlan = $model->plan()->first();

        if ($existingPlan) {
            // Update existing plan
            $existingPlan->update(array_merge([
                'payment_template_id' => $paymentTemplateId,
            ], $values));
            $plan = $existingPlan;
        } else {
            // Create new plan
            $plan = $model->plan()->create(array_merge([
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
            $paymentPlan->update([
                'payment_template_id' => null,
                'booking_fee' => $this->nullableNumber($request->input('booking_fee')),
                'booking_fee_in_per' => $request->boolean('booking_fee_in_per'),
                'development_fee' => $this->nullableNumber($request->input('development_fee')),
                'development_fee_in_per' => $request->boolean('development_fee_in_per'),
                'development_fee_surplus' => $request->boolean('development_fee_surplus'),
                'down_payment' => $this->nullableNumber($request->input('down_payment')),
                'down_payment_in_per' => $request->boolean('down_payment_in_per'),
                'transfer_fee' => $this->nullableNumber($request->input('transfer_fee')),
                'transfer_fee_in_per' => $request->boolean('transfer_fee_in_per'),
                'transfer_fee_surplus' => $request->boolean('transfer_fee_surplus'),
                'confirmation_fee' => $this->nullableNumber($request->input('confirmation_fee')),
                'confirmation_fee_in_per' => $request->boolean('confirmation_fee_in_per'),
                'possession_fee' => $this->nullableNumber($request->input('possession_fee')),
                'possession_fee_in_per' => $request->boolean('possession_fee_in_per'),
                'possession_fee_surplus' => $request->boolean('possession_fee_surplus'),
                'corner_premium' => $this->nullableNumber($request->input('corner_premium')),
                'corner_premium_in_per' => true,
                'park_facing_premium' => $this->nullableNumber($request->input('park_facing_premium')),
                'park_facing_premium_in_per' => true,
                'main_boulevard_premium' => $this->nullableNumber($request->input('main_boulevard_premium')),
                'main_boulevard_premium_in_per' => true,
            ]);

            return back()->with('success', 'Payment plan values updated.');
        } catch (\Exception $e) {
            return back()->with('error', 'An error occurred while updating the payment plan: '.$e->getMessage());
        }
    }

    /**
     * Detaches a single installment attachment (template-linked or custom)
     * from a payment plan.
     */
    public function detachInstallment(PropertyPaymentPlan $paymentPlan, InstallmentTemplate $installmentAttachment)
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
            'planable_id' => 'required|integer',
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
            $model = $modelClass::findOrFail($request->planable_id);

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
     * Eligible (owner-less) property IDs for a society/property selection,
     * optionally narrowed by marla/kanal size range.
     */
    private function getEligiblePropertyIds(array $ids, string $planableType, ?string $sizeRange): array
    {
        $query = $planableType === 'society'
          ? Property::whereHas('block.society', fn ($q) => $q->whereIn('uuid', $ids))
          : Property::whereIn('uuid', $ids);

        $query->whereNull('owner_id');

        if (! $sizeRange) {
            return $query->pluck('id')->toArray();
        }

        [$min, $max] = $this->parseSizeRange($sizeRange);

        return $query->with('block.society', 'floors.units.rooms', 'floors.rooms')
            ->get()
            ->filter(fn ($property) => $this->areaInRange($property, $min, $max))
            ->pluck('id')
            ->toArray();
    }

    private function areaInRange(Property $property, float $min, ?float $max): bool
    {
        $sqft = $property->floors->flatMap->units->flatMap->rooms
            ->merge($property->floors->flatMap->rooms)
            ->sum(fn ($room) => GetArea::calculate($room->dimensions));

        $marlaSize = $property->block->society->marla_size ?: self::DEFAULT_MARLA_SQFT;

        $marla = $sqft / $marlaSize;

        return $marla >= $min && ($max === null || $marla <= $max);
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
     * Print view for a property/unit payment plan. Supports selecting one of
     * the property's applicants (people with inquiries) for a personalized
     * print, or a simple copy with no applicant.
     */
    public function printPlan(Request $request, string $uuid)
    {
        $property = Property::where('uuid', $uuid)->first()
            ?? PropertyUnit::where('uuid', $uuid)->first();

        abort_unless($property, 404);

        $listing = $property->listing;
        $plan = $property->plan;

        $applicants = $listing?->inquiries()->with('visitor')->orderByDesc('created_at')->get() ?? collect();

        $selectedApplicant = $request->query('applicant')
            ? $applicants->firstWhere('uuid', $request->query('applicant'))
            : null;

        $user = auth()->user();
        $fullName = trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));
        $printedByName = $fullName !== '' ? $fullName : null;
        $printedByRole = $user?->getRoleNames()->join(', ') ?: null;

        return view('components.property.payment-plans-print', compact(
            'property', 'listing', 'plan', 'applicants', 'selectedApplicant',
            'printedByName', 'printedByRole'
        ));
    }

    public function getSocietyPropertyIds(Request $request)
    {
        try {
            $request->validate(['society_ids' => 'required|string']);

            $propertyIds = PropertyListing::whereHas('block.society', function ($query) use ($request) {
                $query->whereIn('uuid', explode(',', $request->society_ids));
            })->pluck('uuid')->toArray();

            return response()->json(['property_ids' => $propertyIds, 'count' => count($propertyIds)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getBlockPropertyIds(Request $request, string $blockId)
    {
        try {
            $propertyIds = PropertyListing::where('block_id', $blockId)->pluck('uuid')->toArray();

            return response()->json(['property_ids' => $propertyIds, 'count' => count($propertyIds)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
