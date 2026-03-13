<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SocietySwitcherController extends Controller
{
  public function switch(Request $request)
  {
    $request->validate(['society_id' => 'required|integer']);
    $societyIds = auth()->user()
      ->memberSocieties()
      ->pluck('societies.id')
      ->toArray();

    if (!in_array($request->society_id, $societyIds)) {
      abort(403, 'Unauthorized society.');
    }
    session(['active_society_id' => $request->society_id]);
    return redirect()->back();
  }
}
