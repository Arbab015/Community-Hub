<?php

if (!function_exists('normalize_color')) {

  function normalize_color($color)
  {
    if (!$color) {
      return 'rgba(102,108,232,1)';
    }

    $color = trim($color);


    if (preg_match('/cmyk\((\d+)%?,\s*(\d+)%?,\s*(\d+)%?,\s*(\d+)%?\)/i', $color, $m)) {

      $c = $m[1] / 100;
      $m_ = $m[2] / 100;
      $y = $m[3] / 100;
      $k = $m[4] / 100;

      $r = 255 * (1 - $c) * (1 - $k);
      $g = 255 * (1 - $m_) * (1 - $k);
      $b = 255 * (1 - $y) * (1 - $k);

      return 'rgb(' . round($r) . ',' . round($g) . ',' . round($b) . ')';
    }


    if (preg_match('/hsva\((\d+),\s*(\d+)%?,\s*(\d+)%?,\s*([0-9.]+)\)/i', $color, $m)) {

      $h = $m[1];
      $s = $m[2] / 100;
      $v = $m[3] / 100;
      $a = $m[4];

      $l = $v * (1 - $s / 2);
      $newS = ($l == 0 || $l == 1) ? 0 : ($v - $l) / min($l, 1 - $l);

      return 'hsla('
        . round($h) . ','
        . round($newS * 100) . '%,'
        . round($l * 100) . '%,'
        . $a . ')';
    }


    return $color;
  }
}