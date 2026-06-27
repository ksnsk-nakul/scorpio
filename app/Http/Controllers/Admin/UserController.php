<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => User::with('roles:id,name')->latest()->get(['id','name','email','avatar','created_at']),
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => 'required|exists:roles,name']);
        $user->syncRoles([$data['role']]);
        return back()->with('success', "Role updated to {$data['role']}.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
