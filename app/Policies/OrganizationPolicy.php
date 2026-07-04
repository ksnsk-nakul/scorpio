<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $org): bool
    {
        if ($user->hasRole('admin')) return true;
        return $org->owner_id === $user->id
            || $org->members()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('admin')) return true;
        $plan = $user->currentPlan();
        $orgPlans = ['team', 'business', 'enterprise'];
        return in_array($plan, $orgPlans, true);
    }

    public function update(User $user, Organization $org): bool
    {
        if ($user->hasRole('admin')) return true;
        return $org->owner_id === $user->id;
    }

    public function delete(User $user, Organization $org): bool
    {
        if ($user->hasRole('admin')) return true;
        return $org->owner_id === $user->id;
    }

    public function manageMember(User $user, Organization $org): bool
    {
        if ($user->hasRole('admin')) return true;
        return $org->owner_id === $user->id;
    }
}
