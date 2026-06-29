<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Profile/Index', [
            'user' => auth()->user()->only('id', 'name', 'username', 'email'),
        ]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:64', 'alpha_dash',
                           Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['required', 'email', 'max:255',
                           Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('profile_success', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        auth()->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('password_success', 'Password updated.');
    }
}
