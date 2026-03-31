<?php

namespace App\Helpers;

use App\Models\Society;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SocietyAccessResolver
{
  /**
   * Check if the user is a Society Owner or has a role created by a Society Owner.
   * Returns ['isSocietyScoped' => bool, 'ownedSocietyIds' => Collection|null]
   */
  public static function resolver($login_user)
  {
    $isSocietyOwner = $login_user->hasRole('Society Owner');
    $isRoleByOwner  = false;
    $ownerId        = null;

    if ($isSocietyOwner) {
      $ownerId = $login_user->id;
    } else {
      $userRoleIds = $login_user->roles->pluck('id');
      $creatorId = DB::table('roles')
        ->whereIn('id', $userRoleIds)
        ->whereIn('user_id', function ($q) {
          $q->select('model_id')
            ->from('model_has_roles')
            ->join('roles as r', 'r.id', '=', 'model_has_roles.role_id')
            ->where('r.name', 'Society Owner')
            ->where('model_has_roles.model_type', User::class);
        })
        ->value('user_id');

      if ($creatorId) {
        $isRoleByOwner = true;
        $ownerId       = $creatorId;
      }
    }
    $isSocietyScoped = $isSocietyOwner || $isRoleByOwner;
    return [
      'isSocietyScoped'  => $isSocietyScoped,
      'ownedSocietyIds'  => $isSocietyScoped
        ? Society::where('owner_id', $ownerId)->pluck('id')
        : null,
    ];
  }
}
