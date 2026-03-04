<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;

class ResetPasswordBasic extends Controller
{
  public function index(Request $request)
  {
    $pageConfigs = ['myLayout' => 'blank'];
    $token = $request->route('token');
    return view('content.authentications.auth-reset-password-basic', ['pageConfigs' => $pageConfigs, 'token' => $token, 'email' => $request->email]);
  }

  public function reset(Request $request)
  {

    $request->validate([
      'token' => 'required',
      'email' => 'required|email|exists:users,email',
      'password' => 'required|string|confirmed|min:8',
    ]);

    $status = Password::reset(
      $request->only('email', 'password', 'password_confirmation', 'token'),
      function ($user, $password) {
        $user->password = Hash::make($password);
        $user->save();
      }
    );

    return $status === Password::PASSWORD_RESET
      ? redirect()->route('login')->with('success', 'Password has been reset successfully!')
      : back()->withErrors(['email' => [__($status)]]);
  }
}