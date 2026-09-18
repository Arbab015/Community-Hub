<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use App\Models\User;
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
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        $remember = $request->has('remember-me');

        // Credentials can be an email address or a contact number
        $user = $this->findUserByIdentifier((string) $request->input('email'));

        if ($user && Auth::attempt([
            'email' => $user->email,
            'password' => $request->input('password'),
        ], $remember)) {
            $request->session()->regenerate();

            return redirect()->route('dashboard.analytics')->with('success', 'Login successful!');
        }

        return redirect()->back()
            ->withErrors(['email' => 'Invalid credentials'])
            ->withInput($request->only('email'));
    }

    /**
     * Find a user by email or contact number.
     */
    private function findUserByIdentifier(string $identifier): ?User
    {
        // Exact email match
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $identifier)->first();
        }

        // Exact contact match
        $user = User::where('contact', $identifier)->first();
        if ($user) {
            return $user;
        }

        // Contact without formatting (strip non-digits, e.g. spaces/dashes)
        $digits = preg_replace('/\D+/', '', $identifier);
        if ($digits) {
            return User::whereRaw('REGEXP_REPLACE(contact, "[^0-9]", "") = ?', [$digits])->first();
        }

        return null;
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/auth/login')->with('success', 'Logged out successfully!');
    }
}
