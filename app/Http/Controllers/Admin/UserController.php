<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->get(['id', 'name', 'email', 'avatar', 'created_at', 'plan', 'wallet_balance_paise']);

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

    public function walletAdjust(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        abort_if($user->hasRole('admin'), 403, 'Cannot adjust admin wallet.');

        $data = $request->validate([
            'type'   => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:1|max:100000',
            'note'   => 'required|string|max:255',
        ]);

        $amountPaise = (int) round($data['amount'] * 100);

        try {
            DB::transaction(function () use ($user, $data, $amountPaise) {
                if ($data['type'] === 'credit') {
                    $user->creditWallet($amountPaise, 'adjustment', $data['note']);
                } else {
                    $user->debitWallet($amountPaise, 'adjustment', $data['note']);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $action = $data['type'] === 'credit' ? 'credited' : 'debited';
        return back()->with('success', "Wallet {$action} ₹{$data['amount']} for {$user->name}.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'Cannot delete yourself.');
        abort_if($user->hasRole('admin'), 403, 'Admin accounts cannot be deleted here.');
        $user->delete();
        return back()->with('success', 'User deleted.');
    }
}
