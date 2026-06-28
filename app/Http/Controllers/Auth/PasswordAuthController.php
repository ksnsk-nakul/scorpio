<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class PasswordAuthController extends Controller
{
    public function showRegister()
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => $data['password'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole('viewer');
        Auth::login($user);

        return redirect('/admin/dashboard');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();
        return redirect()->intended('/admin/dashboard');
    }

    public function showForgot()
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));
        return back()->with('status', 'If that email exists, a reset link has been sent.');
    }

    public function showReset(string $token)
    {
        return Inertia::render('Auth/ResetPassword', ['token' => $token]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password])->save();
            Auth::login($user);
        });

        return $status === Password::PasswordReset
            ? redirect('/admin/dashboard')
            : back()->withErrors(['email' => __($status)]);
    }
}
