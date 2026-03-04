<?php

use Illuminate\Support\Facades\Auth;

if (!function_exists('canViewMenu')) {
  function canViewMenu($menu)
  {
    if (isset($menu->permission)) {
      return Auth::user()?->can($menu->permission);
    }

    if (isset($menu->submenu)) {
      foreach ($menu->submenu as $sub) {
        if (canViewMenu($sub)) {
          return true;
        }
      }
      return false;
    }

    return true;
  }
}
