<?php

namespace App\Support;

use App\Models\FbrTaxRate;

/**
 * Resolves which FBR tax rate applies to a property sale.
 *
 * Slab semantics mirror FbrTaxRateController exactly:
 *   - a slab is a (role, value_from, value_to) range holding a filer and a
 *     non_filer row; filer_status selects the row inside the range.
 *   - ranges are HALF-OPEN [value_from, value_to) - a price sitting exactly on
 *     a value_from belongs to the slab STARTING there, never the one ending there.
 *   - a NULL value_to means "and above" (unbounded).
 *
 * Nothing here is hard-coded: the percentage always comes from the table.
 */
final class FbrTaxRateResolver
{
    public const ROLE_BUYER = 'buyer';

    public const FILER = 'filer';

    public const NON_FILER = 'non_filer';

    /**
     * The buyer slab covering $finalPrice for the given filer status.
     *
     * Falls back to the PKR 0 base slab when a gap in the configured ranges
     * leaves the price uncovered, so a rate is never silently null.
     */
    public function resolve(float $finalPrice, string $filerStatus = self::NON_FILER, string $role = self::ROLE_BUYER): ?FbrTaxRate
    {
        $filerStatus = $filerStatus === self::FILER ? self::FILER : self::NON_FILER;

        $match = $this->slabQuery($role, $filerStatus)
            ->where('value_from', '<=', $finalPrice)
            ->where(function ($q) use ($finalPrice) {
                $q->whereNull('value_to')->orWhere('value_to', '>', $finalPrice);
            })
            ->orderByDesc('value_from')
            ->first();

        if ($match) {
            return $match;
        }

        return $this->slabQuery($role, $filerStatus)
            ->where('value_from', 0)
            ->orderBy('value_from')
            ->first();
    }

    /**
     * The percentage that applies, or 0.0 when nothing is configured.
     */
    public function ratePercent(float $finalPrice, string $filerStatus = self::NON_FILER, string $role = self::ROLE_BUYER): float
    {
        return (float) ($this->resolve($finalPrice, $filerStatus, $role)?->rate_percent ?? 0.0);
    }

    private function slabQuery(string $role, string $filerStatus)
    {
        return FbrTaxRate::query()
            ->where('role', $role)
            ->where('filer_status', $filerStatus);
    }
}
