<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['oauth' => 'Google login failed.']);
        }

        $user = DB::transaction(function () use ($googleUser) {
            // Find by google_id first, then fall back to email (handles seeded/existing users)
            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->lockForUpdate()
                ->first();

            if ($user) {
                $user->update([
                    'google_id'         => $googleUser->getId(),
                    'name'              => $googleUser->getName(),
                    'avatar'            => $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                $user = User::create([
                    'google_id'         => $googleUser->getId(),
                    'name'              => $googleUser->getName(),
                    'email'             => $googleUser->getEmail(),
                    'avatar'            => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
            }

            return $user;
        });

        if (! $user->hasAnyRole(['admin', 'editor', 'viewer'])) {
            $user->assignRole('viewer');
        }

        Auth::login($user);

        return redirect()->intended('/admin/dashboard');
    }
}
