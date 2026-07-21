<?php

namespace App\Helpers;

class CurrencyConverter
{
    /**
     * Convert an amount from one currency to another.
     *
     * @param  string  $fromCurrency  Currency the amount is currently in (e.g. listing's stored 'unit')
     * @param  string  $toCurrency  Currency to convert into (e.g. user's selected display currency)
     * @param  array  $ratesToPkr  Map of currency code => how many PKR equal 1 unit of that currency.
     *                             Must include 'PKR' => 1.
     */
    public static function convert(float $amount, string $fromCurrency, string $toCurrency, array $ratesToPkr): float
    {
        $fromCurrency = strtoupper($fromCurrency ?: 'PKR');
        $toCurrency = strtoupper($toCurrency ?: 'PKR');

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $fromRate = $ratesToPkr[$fromCurrency] ?? 1;
        $toRate = $ratesToPkr[$toCurrency] ?? 1;

        // Step 1: normalize to PKR
        $pkrAmount = $fromCurrency === 'PKR' ? $amount : $amount * $fromRate;

        // Step 2: convert PKR into the target currency
        return $toCurrency === 'PKR' ? $pkrAmount : $pkrAmount / $toRate;
    }
}
