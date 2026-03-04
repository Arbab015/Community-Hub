<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
  public function index()
  {

    $pageConfigs = ['myLayout' => 'blank'];
    return view('content.authentications.auth-login', ['pageConfigs' => $pageConfigs]);
  }

  public function login(Request $request)
  {
    $validator = Validator::make($request->all(), [
      'email' => 'required|email|exists:users,email',
      'password' => 'required|string',
    ]);

    if ($validator->fails()) {
      return redirect()->back()
        ->withErrors($validator)
        ->withInput();
    }

    $credentials = $request->only('email', 'password');
    $remember = $request->has('remember-me');

    if (Auth::attempt($credentials, $remember)) {
      $request->session()->regenerate();
      return redirect()->route('dashboard')->with('success', 'Login successful!');
    }

    return redirect()->back()
      ->withErrors(['email' => 'Invalid credentials'])
      ->withInput($request->only('email'));
  }



  public function logout(Request $request)
  {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/auth/login')->with('success', 'Logged out successfully!');
  }
}
