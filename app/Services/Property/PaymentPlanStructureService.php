<?php

namespace App\Services\Property;

use App\Models\InstallmentTemplate;
use App\Models\PaymentTemplate;
use App\Models\Property;
use App\Models\PropertyPaymentPlan;
use App\Models\PropertyReservedPaymentPlan;
use App\Models\PropertyUnit;
use App\Support\InstallmentPlanCalculator;
use App\Support\PaymentPlanPresenter;

class PaymentPlanStructureService
{
    public const FEE_KEYS = [
        ['key' => 'booking_fee', 'label' => 'Booking Fee', 'icon' => 'ti tabler-key'],
        ['key' => 'development_fee', 'label' => 'Development Fee', 'icon' => 'ti tabler-building-community'],
        ['key' => 'down_payment', 'label' => 'Down Payment', 'icon' => 'ti tabler-cash-banknote'],
        ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee', 'icon' => 'ti tabler-file-check'],
        ['key' => 'possession_fee', 'label' => 'Possession Fee', 'icon' => 'ti tabler-home-check'],
    ];

    public const PREMIUM_KEYS = [
        ['key' => 'corner_premium', 'label' => 'Corner Premium', 'icon' => 'ti tabler-corner-down-right-double'],
        ['key' => 'park_facing_premium', 'label' => 'Park Facing Premium', 'icon' => 'ti tabler-trees'],
        ['key' => 'main_boulevard_premium', 'label' => 'Main Boulevard Premium', 'icon' => 'ti tabler-road'],
    ];

    public const SURPLUS_KEYS = ['development_fee', 'possession_fee'];

    private const PREMIUM_FLAGS = [
        'corner_premium' => 'is_corner',
        'park_facing_premium' => 'is_park_facing',
        'main_boulevard_premium' => 'is_main_boulevard_facing',
    ];

    public function build(PropertyUnit|Property $property): array
    {
        $isUnit = $property instanceof PropertyUnit;

        $property->loadMissing([
            'owner',
            'listing',
            'paymentPlan.paymentTemplate',
            'paymentPlan.installmentAttachments.installmentTemplate',
        ]);

        $parentProperty = $isUnit ? $property->floor?->property : $property;
        $society_id = $isUnit ? $parentProperty?->block?->society_id : $property->block?->society_id;

        // A society member only sees an ownership plan tied to their own
        // ownership (current, or the one they transferred away). Everyone else
        // sees the property's active ownership plan.
        $viewer = auth()->user();

        $ownershipPlan = app(PaymentVisibilityService::class)
            ->visiblePlans($property, $viewer)
            ->first(fn ($plan) => $plan->propertyOwner?->isCurrent());

        $plan = $property->paymentPlan;
        $paymentTemplate = $plan?->paymentTemplate;

        $listingPrice = (float) ($plan?->listed_price ?? $property->listing?->price ?? 0);
        $listingUnit = $plan?->listed_unit ?? $property->listing?->unit ?? 'PKR';

        $isCommercial = $parentProperty?->category === 'commercial';
        $isConstructed = $parentProperty?->construction_status === 'constructed';

        $paymentTemplateOptions = PaymentTemplate::where('owner_id', auth()->id())
            ->where('is_commercial', $isCommercial)
            ->where('is_constructed', $isConstructed)->get();

        $allInstallmentTemplates = InstallmentTemplate::where('owner_id', auth()->id())
            ->where('is_commercial', $isCommercial)
            ->where('is_constructed', $isConstructed)->get();

        $attachments = $plan?->installmentAttachments ?? collect();
        $attachedTemplateIds = $attachments->pluck('installment_template_id')->filter()->all();
        $installmentTemplateOptions = $allInstallmentTemplates
            ->reject(fn ($t) => in_array($t->id, $attachedTemplateIds))->values();

        $feeState = [];
        foreach (array_merge(self::FEE_KEYS, self::PREMIUM_KEYS) as $f) {
            $k = $f['key'];
            $hasPlanValue = $plan?->{$k} !== null;
            $feeState[$k] = [
                'value' => $plan?->{$k} ?? $paymentTemplate?->{$k},
                'is_per' => (bool) ($hasPlanValue ? $plan->{$k.'_in_per'} : ($paymentTemplate?->{$k.'_in_per'} ?? false)),
                'surplus' => in_array($k, self::SURPLUS_KEYS)
                  ? (bool) ($hasPlanValue ? $plan->{$k.'_surplus'} : ($paymentTemplate?->{$k.'_surplus'} ?? false))
                  : false,
            ];
        }

        // Premium keys that actually apply to this property (based on its features).
        $featurePremiumKeys = collect(self::PREMIUM_KEYS)
            ->filter(function ($pk) use ($parentProperty) {
                $flag = self::PREMIUM_FLAGS[$pk['key']] ?? null;

                return $flag ? (bool) ($parentProperty?->{$flag} ?? false) : true;
            })
            ->values()
            ->all();

        $premiumCharges = [];
        $premiumsTotal = 0;
        foreach ($featurePremiumKeys as $pk) {
            $raw = (float) ($feeState[$pk['key']]['value'] ?? 0);
            if ($raw <= 0) {
                continue;
            }
            $amount = $listingPrice * $raw / 100;
            $premiumCharges[] = $pk + ['raw' => $raw, 'amount' => $amount];
            $premiumsTotal += $amount;
        }

        $grossAmount = $listingPrice + $premiumsTotal;

        $charges = $includedCharges = $excludedCharges = [];
        $includedTotal = $excludedTotal = 0;

        foreach ([true, false] as $wantSurplus) {
            foreach (self::FEE_KEYS as $fk) {
                $s = $feeState[$fk['key']];
                $raw = (float) ($s['value'] ?? 0);
                if ($raw <= 0 || $s['surplus'] !== $wantSurplus) {
                    continue;
                }

                $amount = $s['is_per'] ? $grossAmount * $raw / 100 : $raw;
                $item = $fk + ['raw' => $raw, 'amount' => $amount, 'is_per' => $s['is_per'], 'surplus' => $wantSurplus];
                $charges[] = $item;

                if ($wantSurplus) {
                    $excludedCharges[] = $item;
                    $excludedTotal += $amount;
                } else {
                    $includedCharges[] = $item;
                    $includedTotal += $amount;
                }
            }
        }

        $installmentPlans = [];
        foreach ($attachments as $idx => $att) {
            $installmentPlans[] = $this->buildInstallment($att, $idx, $grossAmount, $includedTotal, $excludedTotal);
        }
        $defaultPlan = $installmentPlans[0] ?? null; // default plan if there is installment plans attached.

        // Final amount follows the highlighted amounts: Total One-time + Total for
        // Installments (when a plan exists) or Total One-time + Remaining Amount.
        $oneTimeTotal = $excludedTotal + $includedTotal;
        $remainingAmount = $grossAmount - $includedTotal;
        $installmentTotal = $defaultPlan ? $remainingAmount + ($defaultPlan['add_amount'] ?? 0) : null;

        $planStructureSummary = [
            'listingPrice' => $listingPrice,
            'listingUnit' => $listingUnit,
            'premiumCharges' => $premiumCharges,
            'premiumsTotal' => $premiumsTotal,
            'grossAmount' => $grossAmount,
            'excludedCharges' => $excludedCharges,
            'includedCharges' => $includedCharges,
            'excludedTotal' => $excludedTotal,
            'includedTotal' => $includedTotal,
            'totalOneTime' => $oneTimeTotal,
            'remainingAmount' => $remainingAmount,
            'hasDefaultPlan' => ! empty($defaultPlan),
            'extraInstallment' => $defaultPlan['add_amount'] ?? 0,
            'balloonAmount' => $defaultPlan['balloon_amount'] ?? 0,
            'installmentRemaining' => $defaultPlan['installment_remaining'] ?? $remainingAmount,
            'finalAmount' => $oneTimeTotal + ($installmentTotal ?? $remainingAmount),
            'perBalloon' => $defaultPlan['per_balloon'] ?? 0,
            'perInstallment' => $defaultPlan['per_installment'] ?? 0,
            'installmentCount' => $defaultPlan['number_of_installments'] ?? null,
        ];

        $reserved = [];
        if ($ownershipPlan) {
            $reserved = $this->buildReservedStructure($property, $ownershipPlan, $plan, $allInstallmentTemplates);
        }

        return array_merge([
            'isUnit' => $isUnit,
            'entityLabel' => $isUnit ? 'Unit' : 'Property',
            'planableType' => $isUnit ? 'unit' : 'property',
            'ownerableType' => $property::class,
            'ownerableId' => $property->id,
            'propertySocietyId' => $society_id,

            'owner' => $property->owner,
            'ownershipPlan' => $ownershipPlan,
            'unCompletePayments' => $ownershipPlan && $ownershipPlan->status !== 'completed',

            'plan' => $plan,
            'paymentTemplate' => $paymentTemplate,
            'listingPrice' => $listingPrice,
            'listingUnit' => $listingUnit,

            'feeKeys' => self::FEE_KEYS,
            'premiumKeys' => self::PREMIUM_KEYS,
            'featurePremiumKeys' => $featurePremiumKeys,
            'surplusKeys' => self::SURPLUS_KEYS,
            'feeState' => $feeState,
            'paymentTemplateOptions' => $paymentTemplateOptions,
            'allInstallmentTemplates' => $allInstallmentTemplates,
            'installmentTemplateOptions' => $installmentTemplateOptions,

            'premiumCharges' => $premiumCharges,
            'premiumsTotal' => $premiumsTotal,
            'grossAmount' => $grossAmount,
            'charges' => $charges,
            'includedCharges' => $includedCharges,
            'excludedCharges' => $excludedCharges,
            'includedTotal' => $includedTotal,
            'excludedTotal' => $excludedTotal,
            'totalOneTime' => $excludedTotal + $includedTotal,
            'remainingAmount' => $grossAmount - $includedTotal,

            'installmentPlans' => $installmentPlans,
            'defaultPlan' => $defaultPlan,
            'extraInstallment' => $defaultPlan['add_amount'] ?? 0,
            'summaryPerBalloon' => $defaultPlan['per_balloon'] ?? 0,
            'summaryPerInstallment' => $defaultPlan['per_installment'] ?? 0,

            'planStructureSummary' => $planStructureSummary,
        ], $reserved);
    }

    private function buildInstallment($att, int $idx, float $gross, float $included, float $excluded): array
    {
        $val = fn ($f) => $att->{$f} ?? $att->installmentTemplate?->{$f};

        $name = $att->name ?: ($att->installmentTemplate?->name ?: 'Installment Plan');
        $num = (int) $val('number_of_installments');
        $frequency = $val('installment_frequency');

        $addPer = (bool) $val('additional_installment_amount_in_per');
        $addRaw = (float) ($val('additional_installment_amount') ?? 0);
        $addAmount = $addPer ? $gross * $addRaw / 100 : $addRaw;

        $balloonPer = (bool) $val('balloon_payment_in_per');
        $balloonRaw = (float) ($val('balloon_payment') ?? 0);
        $balloonFreq = $val('balloon_payment_frequency');

        $calc = InstallmentPlanCalculator::calculate([
            'listing_price' => $gross,
            'included_charges_total' => $included,
            'excluded_charges_total' => $excluded,
            'additional_amount' => $addAmount,
            'number_of_installments' => $num,
            'installment_frequency' => $frequency,
            'balloon_payment' => $balloonRaw,
            'balloon_payment_in_per' => $balloonPer,
            'balloon_payment_frequency' => $balloonFreq,
        ]);

        return [
            'id' => $att->id,
            'att' => $att,
            'name' => $name,
            'from_template' => $att->installment_template_id !== null,
            'template_name' => $att->installmentTemplate?->name,
            'sequence' => $idx + 1,
            'number_of_installments' => $num,
            'installment_frequency' => $frequency,
            'add_amount' => $addAmount,
            'balloon_amount' => $calc['total_balloon'],
            'balloon_frequency' => $balloonFreq,
            'balloon_count' => $calc['balloon_count'],
            'per_balloon' => $calc['per_balloon'],
            'normal_count' => $calc['normal_count'],
            'grand_total' => $calc['grand_total'],
            'installment_remaining' => $calc['installment_remaining'],
            'per_installment' => $calc['per_installment'],
            'schedule' => $calc['schedule'],
            'total_years' => $calc['total_years'],
            'edit_payload' => json_encode([
                'name' => $name,
                'number_of_installments' => $num,
                'installment_frequency' => $frequency,
                'additional_installment_amount' => $addRaw,
                'additional_installment_amount_in_per' => (int) $addPer,
                'balloon_payment' => $balloonRaw,
                'balloon_payment_in_per' => (int) $balloonPer,
                'balloon_payment_frequency' => $balloonFreq,
            ]),
        ];
    }

    /**
     * Build the display structure for an active ownership / reserved payment
     * plan (committed snapshot). Mirrors what the reserved blade used to query
     * inline so both blades are served by this single service.
     */
    private function buildReservedStructure(
        PropertyUnit|Property $property,
        PropertyReservedPaymentPlan $ownershipPlan,
        ?PropertyPaymentPlan $plan,
        $allInstallmentTemplates
    ): array {
        $data = PaymentPlanPresenter::buildFromSnapshot($ownershipPlan);
        $unit = $data['listing_unit'];

        $allFeeRows = collect($data['fee_rows'] ?? [])
            ->reject(fn ($r) => ($r['label'] ?? '') === 'Listed Price')
            ->values();
        $excludedFees = $allFeeRows->where('note', 'Excluded')->values();
        $includedFees = $allFeeRows->where('note', 'Included')->values();
        $premiumRows = $data['premium_rows'] ?? [];
        $grossAmount = $data['gross_amount'];
        $excludedTotal = $data['excluded_total'];
        $includedTotal = $data['included_total'];
        $remainingAmount = $data['total_price'] - $includedTotal;

        $installment = $data['installment_rows'][0] ?? null;
        $extraAmount = $installment['additional_amount'] ?? 0;
        $totalBalloon = $installment['balloon'] ?? 0;
        $installmentRemaining = $installment['installment_remaining'] ?? $remainingAmount;

        $planAttachments = collect($plan?->installmentAttachments ?? []);
        $attachedTemplateIds = $planAttachments->pluck('installment_template_id')->filter()->all();
        $attachedNames = $planAttachments
            ->map(fn ($att) => mb_strtolower(trim((string) (($att->name ?? '') ?: ($att->installmentTemplate?->name ?? '')))))
            ->filter()
            ->all();

        $addInstallmentTemplateOptions = $allInstallmentTemplates
            ->reject(fn ($tpl) => in_array($tpl->id, $attachedTemplateIds))
            ->reject(fn ($tpl) => in_array(mb_strtolower(trim((string) $tpl->name)), $attachedNames))
            ->values();

        $addInstallmentOptions = collect();

        // Attached plans first - marked as "applied" to the property's payment plan.
        foreach ($planAttachments as $att) {
            $addInstallmentOptions->push([
                'source' => 'attachment:'.$att->id,
                'name' => trim((string) (($att->name ?? '') ?: ($att->installmentTemplate?->name ?? ''))) ?: 'Installment Plan',
                'info' => $this->installmentSummary(
                    $this->needInstallmentValue($att, 'number_of_installments'),
                    $this->needInstallmentValue($att, 'installment_frequency'),
                    (float) ($this->needInstallmentValue($att, 'balloon_payment') ?? 0) > 0
                ),
                'applied' => true,
                'num' => (int) ($this->needInstallmentValue($att, 'number_of_installments') ?? 0),
                'frequency' => $this->needInstallmentValue($att, 'installment_frequency') ?? '',
                'additional' => $this->needInstallmentValue($att, 'additional_installment_amount') ?? 0,
                'additional_in_per' => (int) ($this->needInstallmentValue($att, 'additional_installment_amount_in_per') ?? 0),
                'balloon' => $this->needInstallmentValue($att, 'balloon_payment') ?? 0,
                'balloon_in_per' => (int) ($this->needInstallmentValue($att, 'balloon_payment_in_per') ?? 0),
                'balloon_frequency' => $this->needInstallmentValue($att, 'balloon_payment_frequency') ?? '',
            ]);
        }

        // Available category-matched templates.
        foreach ($addInstallmentTemplateOptions as $tpl) {
            $addInstallmentOptions->push([
                'source' => 'template:'.$tpl->id,
                'name' => $tpl->name,
                'info' => $this->installmentSummary(
                    $tpl->number_of_installments,
                    $tpl->installment_frequency,
                    (float) ($tpl->balloon_payment ?? 0) > 0
                ),
                'applied' => false,
                'num' => (int) ($tpl->number_of_installments ?? 0),
                'frequency' => $tpl->installment_frequency ?? '',
                'additional' => $tpl->additional_installment_amount ?? 0,
                'additional_in_per' => (int) ($tpl->additional_installment_amount_in_per ?? 0),
                'balloon' => $tpl->balloon_payment ?? 0,
                'balloon_in_per' => (int) ($tpl->balloon_payment_in_per ?? 0),
                'balloon_frequency' => $tpl->balloon_payment_frequency ?? '',
            ]);
        }

        return [
            'data' => $data,
            'unit' => $unit,
            'allFeeRows' => $allFeeRows,
            'excludedFees' => $excludedFees,
            'includedFees' => $includedFees,
            'premiumRows' => $premiumRows,
            'grossAmount' => $grossAmount,
            'excludedTotal' => $excludedTotal,
            'includedTotal' => $includedTotal,
            'remainingAmount' => $remainingAmount,
            'installment' => $installment,
            'extraAmount' => $extraAmount,
            'totalBalloon' => $totalBalloon,
            'installmentRemaining' => $installmentRemaining,
            'addInstallmentOptions' => $addInstallmentOptions,
        ];
    }

    /**
     * Read a snapshot/template field, falling back to the linked template.
     */
    private function needInstallmentValue($att, string $field)
    {
        $value = $att->{$field} ?? null;
        if ($value !== null && $value !== '') {
            return $value;
        }

        return $att->installmentTemplate?->{$field} ?? null;
    }

    /**
     * Compact useful summary: "12x Monthly · + Balloon".
     */
    private function installmentSummary($num, $freq, $hasBalloon): string
    {
        $parts = [];
        if ((int) $num > 0) {
            $parts[] = (int) $num.'x '.ucfirst($freq ?: 'installments');
        }
        if ($hasBalloon) {
            $parts[] = '+ Balloon';
        }

        return implode(' · ', $parts) ?: 'Installment Plan';
    }
}
