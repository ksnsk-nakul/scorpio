<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\OrganizationAchievement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $this->authorize('viewAny', Organization::class);

        if ($user->hasRole('admin')) {
            $orgs = Organization::with('owner:id,name,email')->get();
        } else {
            $orgs = Organization::where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                  ->orWhereHas('members', fn ($m) => $m->where('user_id', $user->id));
            })->with('owner:id,name,email')->get();
        }

        return Inertia::render('Admin/Organizations/Index', [
            'organizations' => $orgs,
        ]);
    }

    public function show(Organization $organization): Response
    {
        $this->authorize('view', $organization);

        $organization->load([
            'owner:id,name,email,username',
            'members.user:id,name,email,username',
            'achievements.user:id,name,username',
        ]);

        return Inertia::render('Admin/Organizations/Show', [
            'organization' => $organization,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        $data = $request->validate([
            'name'              => 'required|string|max:100',
            'slug'              => 'required|string|max:100|unique:organizations,slug|regex:/^[a-z0-9\-]+$/',
            'description'       => 'nullable|string|max:500',
            'white_label'       => 'boolean',
            'custom_brand_name' => 'nullable|string|max:100',
        ]);

        $org = Organization::create([
            ...$data,
            'owner_id' => auth()->id(),
            'plan'     => auth()->user()->currentPlan(),
        ]);

        return redirect()->route('admin.organizations.show', $org)->with('success', 'Organization created.');
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'name'              => 'sometimes|string|max:100',
            'description'       => 'nullable|string|max:500',
            'white_label'       => 'boolean',
            'custom_brand_name' => 'nullable|string|max:100',
        ]);

        $organization->update($data);
        return back()->with('success', 'Organization updated.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);
        $organization->delete();
        return redirect()->route('admin.organizations.index')->with('success', 'Organization deleted.');
    }

    public function addMember(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMember', $organization);

        $limit = $organization->memberLimit();
        if ($limit !== null && $organization->members()->count() >= $limit) {
            return back()->withErrors(['members' => 'Member limit reached for your plan.']);
        }

        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'role'  => 'required|in:viewer,editor',
        ]);

        $user = User::where('email', $data['email'])->firstOrFail();

        $member = OrganizationMember::firstOrCreate(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => $data['role']]
        );

        $message = $member->wasRecentlyCreated ? 'Member added.' : 'User is already a member.';
        return back()->with('success', $message);
    }

    public function removeMember(Organization $organization, OrganizationMember $member): RedirectResponse
    {
        $this->authorize('manageMember', $organization);
        abort_if($member->organization_id !== $organization->id, 404);
        $member->delete();
        return back()->with('success', 'Member removed.');
    }

    public function addAchievement(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $data = $request->validate([
            'user_id'     => 'required|integer|exists:users,id',
            'title'       => 'required|string|max:150',
            'icon'        => 'nullable|string|max:50',
            'achieved_at' => 'nullable|date',
            'sort_order'  => 'integer|min:0',
        ]);

        OrganizationAchievement::create([
            ...$data,
            'organization_id' => $organization->id,
        ]);

        return back()->with('success', 'Achievement added.');
    }

    public function removeAchievement(Organization $organization, OrganizationAchievement $achievement): RedirectResponse
    {
        $this->authorize('update', $organization);
        abort_if($achievement->organization_id !== $organization->id, 404);
        $achievement->delete();
        return back()->with('success', 'Achievement removed.');
    }
}
