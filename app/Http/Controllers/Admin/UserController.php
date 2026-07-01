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
        // Exclude admin-role accounts — they cannot be demoted or removed via this UI.
        $users = User::with('roles:id,name')
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin'))
            ->latest()
            ->get(['id', 'name', 'email', 'avatar', 'created_at', 'plan']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => Role::where('name', '!=', 'admin')->orderBy('name')->pluck('name'),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        abort_if($user->hasRole('admin'), 403, 'Admin accounts cannot be modified here.');
        $data = $request->validate(['role' => 'required|in:editor,viewer']);
        $user->syncRoles([$data['role']]);
        return back()->with('success', "Role updated to {$data['role']}.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        abort_if($user->hasRole('admin'), 403, 'Admin accounts cannot be deleted here.');
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
