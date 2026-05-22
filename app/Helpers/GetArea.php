<?php

namespace App\Helpers;

class GetArea
{
    public static function calculate($dimensions): float
    {
        $sides = collect($dimensions)->pluck('size')->map(fn ($v) => (float) $v)->toArray();
        $names = collect($dimensions)->pluck('name')->map(fn ($v) => strtolower((string) $v))->toArray();
        $count = count($sides);

        if ($count === 0) {
            return 0.0;
        }

        $nameMap = array_combine($names, $sides);

        return match (true) {

            // 2 sides — rectangle: L × W
            $count === 2 => $sides[0] * $sides[1],

            // 3 sides — irregular plot:
            // Sort sides. Use the longest as one axis,
            // average the two shorter as the other axis.
            // Works for any combination regardless of shape or values.
            $count === 3 => (function () use ($sides) {
                $s = $sides;
                rsort($s); // [largest, mid, smallest]

                return $s[0] * (($s[1] + $s[2]) / 2);
            })(),

            // 4 sides — named front/back/left/right: proper trapezoid
            // otherwise: sort, pair opposite sides (index 0↔2, 1↔3)
            $count === 4 => (function () use ($sides, $nameMap) {
                if (isset($nameMap['front'], $nameMap['back'], $nameMap['left'], $nameMap['right'])) {
                    return (($nameMap['front'] + $nameMap['back']) / 2)
                      * (($nameMap['left'] + $nameMap['right']) / 2);
                }
                $s = $sides;
                sort($s); // [s0, s1, s2, s3] smallest→largest

                // Pair: (s0,s3) are opposite, (s1,s2) are opposite
                return (($s[0] + $s[3]) / 2) * (($s[1] + $s[2]) / 2);
            })(),

            // 5–8 sides — average side length → regular polygon area formula
            // (best approximation for irregular polygons from side lengths only)
            $count === 5 => (function () use ($sides) {
                $s = array_sum($sides) / 5;

                return (5 * $s * $s) / (4 * tan(M_PI / 5));
            })(),

            $count === 6 => (function () use ($sides) {
                $s = array_sum($sides) / 6;

                return (3 * sqrt(3) / 2) * $s * $s;
            })(),

            $count === 7 => (function () use ($sides) {
                $s = array_sum($sides) / 7;

                return (7 * $s * $s) / (4 * tan(M_PI / 7));
            })(),

            $count === 8 => (function () use ($sides) {
                $s = array_sum($sides) / 8;

                return 2 * (1 + sqrt(2)) * $s * $s;
            })(),

            default => 0.0,
        };
    }
}
