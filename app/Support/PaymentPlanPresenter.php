<?php

namespace App\Support;

use App\Models\PropertyUnit;

class PaymentPlanPresenter
{
    /**
     * Build all display-ready values for a property/unit payment plan.
     *
     * @param  \App\Models\Property|\App\Models\PropertyUnit  $property
     * @return array{
     *     is_unit: bool,
     *     listing_price: float,
     *     listing_unit: string,
     *     property_name: string,
     *     property_address: string,
     *     property_ref: string,
     *     property_no: string,
     *     fee_rows: array<int, array{label: string, detail: string, note: string, amount: float}>,
     *     included_total: float,
     *     excluded_total: float,
     *     total_price: float,
     *     installment_rows: array<int, array{name: string, num: int, frequency: ?string, balloon_frequency: ?string, balloon_count: int, per_balloon: int, normal_count: int, per_installment: int, schedule: array<int, array{no: int, type: string, balloon_no: ?int, amount: int, cumulative: int}>, total: int}>,
     *     type_display: string,
     * }
     */
    public static function build($property, ?string $propertyName = null, ?string $propertyAddress = null, ?array $attachmentUuids = null, bool $includeInstallments = true): array
    {
        $isUnit = $property instanceof PropertyUnit;
        $listing = $property->listing;
        $plan = $property->paymentPlan;
        $listingPrice = $listing?->price ?? 0;
        $listingUnit = $listing?->unit ?? 'PKR';

        $paymentTemplate = $plan?->paymentTemplate;

        $feeKeys = [
            ['key' => 'booking_fee', 'label' => 'Booking Fee'],
            ['key' => 'development_fee', 'label' => 'Development Fee'],
            ['key' => 'down_payment', 'label' => 'Down Payment'],
            ['key' => 'transfer_fee', 'label' => 'Transfer Fee'],
            ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee'],
            ['key' => 'possession_fee', 'label' => 'Possession Fee'],
        ];
        $premiumKeys = [
            ['key' => 'corner_premium', 'label' => 'Corner Premium'],
            ['key' => 'park_facing_premium', 'label' => 'Park Facing Premium'],
            ['key' => 'main_boulevard_premium', 'label' => 'Main Boulevard Premium'],
        ];
        $surplusKeys = ['development_fee', 'transfer_fee', 'possession_fee'];

        $feeValue = fn ($key) => $plan?->{$key} ?? $paymentTemplate?->{$key};
        $feeIsPer = function ($key) use ($plan, $paymentTemplate) {
            if ($plan && $plan->{$key} !== null) {
                return (bool) $plan->{$key.'_in_per'};
            }

            return (bool) ($paymentTemplate?->{$key.'_in_per'} ?? false);
        };
        $feeIsSurplus = function ($key) use ($plan, $paymentTemplate, $surplusKeys) {
            if (! in_array($key, $surplusKeys)) {
                return false;
            }
            if ($plan && $plan->{$key} !== null) {
                return (bool) $plan->{$key.'_surplus'};
            }

            return (bool) ($paymentTemplate?->{$key.'_surplus'} ?? false);
        };

        $fmtRaw = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        $feeRows = [];
        $includedTotal = 0;
        $excludedTotal = 0;

        foreach ($feeKeys as $fk) {
            $raw = (float) ($feeValue($fk['key']) ?? 0);
            if ($raw <= 0) {
                continue;
            }

            $isPer = $feeIsPer($fk['key']);
            $amount = $isPer ? $listingPrice * $raw / 100 : $raw;
            $surplus = $feeIsSurplus($fk['key']);

            $feeRows[] = [
                'label' => $fk['label'],
                'detail' => $isPer ? $fmtRaw($raw).'% of listed price' : number_format($raw, 2).' '.$listingUnit,
                'note' => $surplus ? 'Excluded' : 'Included',
                'amount' => $amount,
            ];
            if ($surplus) {
                $excludedTotal += $amount;
            } else {
                $includedTotal += $amount;
            }
        }

        $premiumFeatureFlags = [
            'corner_premium' => 'is_corner',
            'park_facing_premium' => 'is_park_facing',
            'main_boulevard_premium' => 'is_main_boulevard_facing',
        ];
        $premiumSource = $isUnit ? ($property->floor?->property ?? null) : $property;

        foreach ($premiumKeys as $pk) {
            $featureField = $premiumFeatureFlags[$pk['key']] ?? null;
            if ($featureField && ! (bool) ($premiumSource->{$featureField} ?? false)) {
                continue;
            }

            $raw = (float) ($feeValue($pk['key']) ?? 0);
            if ($raw <= 0) {
                continue;
            }

            $amount = $listingPrice * $raw / 100;
            $feeRows[] = [
                'label' => $pk['label'],
                'detail' => $fmtRaw($raw).'% of listed price',
                'note' => 'Excluded',
                'amount' => $amount,
            ];
            $excludedTotal += $amount;
        }

        array_unshift($feeRows, [
            'label' => 'Listed Price',
            'detail' => 'Base price',
            'note' => '',
            'amount' => $listingPrice,
        ]);

        $totalPrice = $listingPrice + $excludedTotal;

        $installmentRows = [];
        if ($includeInstallments) {
            $attachments = $plan?->installmentAttachments ?? collect();
            if ($attachmentUuids) {
                $attachments = $attachments->whereIn('uuid', $attachmentUuids)->values();
            }

            foreach ($attachments as $attachment) {
                $template = $attachment->installmentTemplate;
                $name = ($attachment->name ?: '') ?: ($template?->name ?: 'Installment Plan');
                $num = (int) ($attachment->number_of_installments ?? $template?->number_of_installments ?? 0);
                $frequency = $attachment->installment_frequency ?? $template?->installment_frequency;

                $addInPercent = (bool) ($attachment->additional_installment_amount_in_per ?? $template?->additional_installment_amount_in_per ?? false);
                $addRaw = (float) ($attachment->additional_installment_amount ?? $template?->additional_installment_amount ?? 0);
                $addAmount = $addInPercent ? $listingPrice * $addRaw / 100 : $addRaw;

                $balloonInPercent = (bool) ($attachment->balloon_payment_in_per ?? $template?->balloon_payment_in_per ?? false);
                $balloonRaw = (float) ($attachment->balloon_payment ?? $template?->balloon_payment ?? 0);

                $calc = InstallmentPlanCalculator::calculate([
                    'listing_price' => $listingPrice,
                    'included_charges_total' => $includedTotal,
                    'excluded_charges_total' => $excludedTotal,
                    'additional_amount' => $addAmount,
                    'number_of_installments' => $num,
                    'installment_frequency' => $frequency,
                    'balloon_payment' => $balloonRaw,
                    'balloon_payment_in_per' => $balloonInPercent,
                    'balloon_payment_frequency' => $attachment->balloon_payment_frequency ?? $template?->balloon_payment_frequency,
                ]);

                $installmentRows[] = [
                    'name' => $name,
                    'num' => $num,
                    'frequency' => $frequency,
                    'balloon_frequency' => $attachment->balloon_payment_frequency ?? $template?->balloon_payment_frequency,
                    'balloon' => $calc['total_balloon'],
                    'balloon_count' => $calc['balloon_count'],
                    'per_balloon' => $calc['per_balloon'],
                    'normal_count' => $calc['normal_count'],
                    'per_installment' => $calc['per_installment'],
                    'additional_amount' => $addAmount,
                    'gross_amount' => $calc['gross_amount'],
                    'remaining_amount' => $calc['remaining_amount'],
                    'extra_installments' => $calc['extra_installments'],
                    'grand_total' => $calc['grand_total'],
                    'installment_remaining' => $calc['installment_remaining'],
                    'schedule_total' => $calc['schedule_total'],
                    'schedule' => $calc['schedule'],
                    'fee_rows' => $feeRows,
                    'total' => $calc['plan_total'],
                ];
            }
        }

        return [
            'is_unit' => $isUnit,
            'listing_price' => $listingPrice,
            'listing_unit' => $listingUnit,
            'property_name' => $propertyName ?: self::planPropertyName($property, $isUnit),
            'property_address' => $propertyAddress ?: self::planPropertyAddress($property, $isUnit),
            'property_ref' => self::propertyReference($property, $isUnit),
            'property_no' => $isUnit
              ? (string) ($property->floor?->property?->property_no ?? '')
              : (string) ($property->property_no ?? ''),
            'fee_rows' => $feeRows,
            'included_total' => $includedTotal,
            'excluded_total' => $excludedTotal,
            'total_price' => $totalPrice,
            'installment_rows' => $installmentRows,
            'type_display' => $plan ? ($paymentTemplate?->name ?: 'Custom Plan') : '-',
        ];
    }

    private static function planPropertyName($property, bool $isUnit): string
    {
        return $isUnit
          ? ($property->unit_name ?: 'Unit')
          : ($property->name ?: ucwords($property->type));
    }

    private static function planPropertyAddress($property, bool $isUnit): string
    {
        $block = $isUnit ? ($property->floor?->property?->block ?? null) : ($property->block ?? null);
        $society = $block?->society;
        $address = [];

        if ($isUnit) {
            if ($property->floor?->floor_type) {
                $address[] = 'Floor '.$property->floor->floor_type;
            }
            $parent = $property->floor?->property;
            if ($parent) {
                if ($parent->name) {
                    $address[] = $parent->name;
                }
                if ($parent->property_no) {
                    $address[] = 'Plot #'.$parent->property_no;
                }
            }
        } else {
            if ($property->street) {
                $address[] = $property->street;
            }
            if ($property->landmark) {
                $address[] = $property->landmark;
            }
        }

        if ($block?->name) {
            $address[] = $block->name;
        }
        if ($society?->name) {
            $address[] = $society->name;
        }
        if ($society?->city) {
            $address[] = ucfirst($society->city);
        }
        if ($society?->country) {
            $address[] = $society->country;
        }

        return implode(', ', array_filter($address));
    }

    private static function propertyReference($property, bool $isUnit): string
    {
        return $isUnit
          ? ($property->unit_name ?: 'Unit')
          : ($property->property_no ?: ($property->name ?: 'Property'));
    }
}
