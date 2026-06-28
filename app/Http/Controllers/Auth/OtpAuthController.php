<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\LoginOtpMail;
use App\Models\LoginToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class OtpAuthController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Otp');
    }

    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginToken::where('email', $request->email)->delete();
        LoginToken::create([
            'email'      => $request->email,
            'token'      => $otp,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($request->email)->send(new LoginOtpMail($otp));

        return back()->with('otpSent', true);
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $record = LoginToken::where('email', $data['email'])
            ->where('token', $data['otp'])
            ->first();

        if (! $record || $record->isExpired()) {
            return back()->withErrors(['otp' => 'Invalid or expired code.']);
        }

        $record->delete();

        $user = User::firstOrCreate(
            ['email' => $data['email']],
            ['name' => explode('@', $data['email'])[0], 'email_verified_at' => now()]
        );

        if (! $user->hasAnyRole(['admin', 'editor', 'viewer'])) {
            $user->assignRole('viewer');
        }

        Auth::login($user);
        return redirect()->intended('/admin/dashboard');
    }
}
