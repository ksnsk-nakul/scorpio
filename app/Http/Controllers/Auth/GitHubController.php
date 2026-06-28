<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class GitHubController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback()
    {
        try {
            $githubUser = Socialite::driver('github')->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['oauth' => 'GitHub login failed.']);
        }

        $user = DB::transaction(function () use ($githubUser) {
            $user = User::where('github_id', $githubUser->getId())
                ->orWhere('email', $githubUser->getEmail())
                ->lockForUpdate()
                ->first();

            if ($user) {
                $user->update([
                    'github_id'         => $githubUser->getId(),
                    'name'              => $user->name ?: $githubUser->getName(),
                    'avatar'            => $user->avatar ?: $githubUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
            } else {
                $user = User::create([
                    'github_id'         => $githubUser->getId(),
                    'name'              => $githubUser->getName() ?: $githubUser->getNickname(),
                    'email'             => $githubUser->getEmail(),
                    'avatar'            => $githubUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
            }

            if (! $user->hasAnyRole(['admin', 'editor', 'viewer'])) {
                $user->assignRole('viewer');
            }

            return $user;
        });

        Auth::login($user);
        return redirect()->intended('/admin/dashboard');
    }
}
