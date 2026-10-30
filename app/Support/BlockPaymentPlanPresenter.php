<?php

namespace App\Support;

use App\Models\Block;
use App\Models\BlockPaymentPlan;
use App\Models\BlockPriceRange;
use App\Models\InstallmentTemplate;

/**
 * Builds display-ready values for a block-level payment plan print.
 */
class BlockPaymentPlanPresenter
{
    public static function build(Block $block, BlockPaymentPlan $blockPlan, BlockPriceRange $priceRange, float $size, array $features = []): array
    {
        $rate = (float) $priceRange->price;
        $priceUnit = $priceRange->price_unit;
        $listingPrice = $rate * $size;
        $listingUnit = 'PKR';
        $paymentTemplate = $blockPlan->paymentTemplate;

        $feeKeys = [
            ['key' => 'booking_fee', 'label' => 'Booking Fee'],
            ['key' => 'development_fee', 'label' => 'Development Fee'],
            ['key' => 'down_payment', 'label' => 'Down Payment'],
            //            ['key' =>  ],
            ['key' => 'confirmation_fee', 'label' => 'Confirmation Fee'],
            ['key' => 'possession_fee', 'label' => 'Possession Fee'],
        ];
        $premiumKeys = [
            ['key' => 'corner_premium', 'label' => 'Corner Premium'],
            ['key' => 'park_facing_premium', 'label' => 'Park Facing Premium'],
            ['key' => 'main_boulevard_premium', 'label' => 'Main Boulevard Premium'],
        ];
        $surplusKeys = ['development_fee', 'possession_fee'];

        $fmtRaw = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        // ── Step 1: Compute premiums (perks) — % of listed price ──
        $premiumRows = [];
        $premiumsTotal = 0;
        $premiumOptions = [];

        foreach ($premiumKeys as $pk) {
            $raw = (float) ($paymentTemplate?->{$pk['key']} ?? 0);
            if ($raw <= 0) {
                continue;
            }
            $selected = in_array($pk['key'], $features);
            $amount = $listingPrice * $raw / 100;
            $premiumOptions[] = [
                'label' => $pk['label'],
                'percent' => $fmtRaw($raw),
                'amount' => $amount,
                'selected' => $selected,
            ];
            if (! $selected) {
                continue;
            }
            $premiumRows[] = [
                'label' => $pk['label'],
                'detail' => $fmtRaw($raw).'% of listed price',
                'note' => 'Premium',
                'amount' => $amount,
            ];
            $premiumsTotal += $amount;
        }

        // ── Step 2: Gross Amount = Listed + Premiums ──
        $grossAmount = $listingPrice + $premiumsTotal;

        // ── Step 3: Template fees — excluded (% of gross), included (% of gross) ──
        $feeRows = [];
        $includedTotal = 0;
        $excludedTotal = 0;

        foreach ($feeKeys as $fk) {
            $raw = (float) ($paymentTemplate?->{$fk['key']} ?? 0);
            if ($raw <= 0) {
                continue;
            }
            $isPer = (bool) ($paymentTemplate?->{$fk['key'].'_in_per'} ?? false);
            $surplus = in_array($fk['key'], $surplusKeys)
              && (bool) ($paymentTemplate?->{$fk['key'].'_surplus'} ?? false);
            if (! $surplus) {
                continue;
            }

            $amount = $isPer ? $grossAmount * $raw / 100 : $raw;
            $feeRows[] = [
                'label' => $fk['label'],
                'detail' => $isPer ? $fmtRaw($raw).'% of gross' : number_format($raw, 2).' '.$listingUnit,
                'note' => 'Excluded',
                'amount' => $amount,
            ];
            $excludedTotal += $amount;
        }

        $includedBase = $grossAmount;
        foreach ($feeKeys as $fk) {
            $raw = (float) ($paymentTemplate?->{$fk['key']} ?? 0);
            if ($raw <= 0) {
                continue;
            }
            $isPer = (bool) ($paymentTemplate?->{$fk['key'].'_in_per'} ?? false);
            $surplus = in_array($fk['key'], $surplusKeys)
              && (bool) ($paymentTemplate?->{$fk['key'].'_surplus'} ?? false);
            if ($surplus) {
                continue;
            }

            $amount = $isPer ? $includedBase * $raw / 100 : $raw;
            $feeRows[] = [
                'label' => $fk['label'],
                'detail' => $isPer ? $fmtRaw($raw).'% of gross' : number_format($raw, 2).' '.$listingUnit,
                'note' => 'Included',
                'amount' => $amount,
            ];
            $includedTotal += $amount;
        }

        array_unshift($feeRows, [
            'label' => 'Listed Price',
            'detail' => 'Base price (rate x size)',
            //            'note' =>
            'amount' => $listingPrice,
        ]);

        $totalPrice = $grossAmount;

        $installmentRows = [];
        $templates = InstallmentTemplate::whereIn('id', (array) ($blockPlan->installment_template_ids ?? []))->orderBy('id')->get();

        foreach ($templates as $template) {
            $name = $template->name ?: 'Installment Plan';
            $num = (int) $template->number_of_installments;
            $frequency = $template->installment_frequency;

            $addInPercent = (bool) $template->additional_installment_amount_in_per;
            $addRaw = (float) $template->additional_installment_amount;
            $addAmount = $addInPercent ? $grossAmount * $addRaw / 100 : $addRaw;

            $balloonInPercent = (bool) $template->balloon_payment_in_per;
            $balloonRaw = (float) $template->balloon_payment;

            $calc = InstallmentPlanCalculator::calculate([
                'listing_price' => $grossAmount,
                'included_charges_total' => $includedTotal,
                'excluded_charges_total' => $excludedTotal,
                'additional_amount' => $addAmount,
                'number_of_installments' => $num,
                'installment_frequency' => $frequency,
                'balloon_payment' => $balloonRaw,
                'balloon_payment_in_per' => $balloonInPercent,
                'balloon_payment_frequency' => $template->balloon_payment_frequency,
            ]);

            $schedule = $calc['schedule'];
            $globalSeqNo = 1;
            $installmentSeqNo = 1;
            $balloonSeqNo = 1;

            foreach ($schedule as &$item) {
                $item['no'] = $globalSeqNo++;

                if ($item['type'] === 'balloon') {
                    $item['balloon_no'] = $balloonSeqNo++;
                    $item['inst_no'] = null;
                } else {
                    $item['inst_no'] = $installmentSeqNo++;
                    $item['balloon_no'] = null;
                }
            }

            $installmentRows[] = [
                'name' => $name,
                'num' => $num,
                'frequency' => $frequency,
                'balloon_frequency' => $template->balloon_payment_frequency,
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
                'schedule' => $schedule,
                'fee_rows' => $feeRows,
                'total' => $calc['plan_total'],
            ];
        }

        $parts = [];
        $society = $block->society;
        if ($society) {
            $parts = array_filter([$society->name, ucfirst($society->city ?? ''), $society->country]);
        }
        if ($block->name) {
            $parts[] = $block->name;
        }

        return [
            'block_name' => $block->name,
            'block_address' => implode(', ', array_filter($parts)),
            'listing_price' => $listingPrice,
            'listing_unit' => $listingUnit,
            'size' => $size,
            'rate' => $rate,
            'price_unit' => $priceUnit,
            'is_commercial' => (bool) $blockPlan->is_commercial,
            'is_constructed' => (bool) $blockPlan->is_constructed,
            'payment_template_name' => $paymentTemplate?->name,
            'fee_rows' => $feeRows,
            'premium_rows' => $premiumRows,
            'premium_options' => $premiumOptions,
            'premiums_total' => $premiumsTotal,
            'included_total' => $includedTotal,
            'excluded_total' => $excludedTotal,
            'gross_amount' => $grossAmount,
            'total_price' => $totalPrice,
            'installment_rows' => $installmentRows,
        ];
    }
}
