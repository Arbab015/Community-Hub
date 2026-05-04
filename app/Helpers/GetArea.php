<?php

namespace App\Helpers;
class GetArea
{
    public static function calculate($dimensions): float
    {
        $sides = collect($dimensions)->pluck('size')->map(fn ($v) => (float) $v)->toArray();
        $names = collect($dimensions)->pluck('name')->map(fn ($v) => (string) $v)->toArray();
        $count = count($sides);

        return match ($count) {
            2 => (function () use ($sides, $names) {
                $names = array_map('strtolower', $names);
                if (in_array('length', $names) && in_array('width', $names)) {
                    [$a, $b] = $sides;
                    return $a * $b;
                }
                return 0.0;
            })(),
            // TRIANGLE — Heron's Formula
            3 => (function () use ($sides) {
                [$a, $b, $c] = $sides;
                $s = ($a + $b + $c) / 2;

                return sqrt($s * ($s - $a) * ($s - $b) * ($s - $c));
            })(),

            // QUADRILATERAL — Brahmagupta's Formula
            4 => (function () use ($sides) {
                [$a, $b, $c, $d] = $sides;
                $s = ($a + $b + $c + $d) / 2;

                return sqrt(($s - $a) * ($s - $b) * ($s - $c) * ($s - $d));
            })(),

            // PENTAGON — (5 * s²) / (4 * tan(π/5))
            5 => (function () use ($sides) {
                $s = array_sum($sides) / 5;

                return (5 * pow($s, 2)) / (4 * tan(M_PI / 5));
            })(),

            // HEXAGON — simplified: (3√3 / 2) * s²
            6 => (function () use ($sides) {
                $s = array_sum($sides) / 6;

                return (3 * sqrt(3) / 2) * pow($s, 2);
            })(),

            // HEPTAGON — (7 * s²) / (4 * tan(π/7))
            7 => (function () use ($sides) {
                $s = array_sum($sides) / 7;

                return (7 * pow($s, 2)) / (4 * tan(M_PI / 7));
            })(),

            // OCTAGON — simplified: 2(1+√2) * s²
            8 => (function () use ($sides) {
                $s = array_sum($sides) / 8;

                return 2 * (1 + sqrt(2)) * pow($s, 2);
            })(),
            default => 0.0,
        };
    }
}
