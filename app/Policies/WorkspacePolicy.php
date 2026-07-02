<?php
namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->user_id || $user->hasRole('admin');
    }
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->id === $workspace->user_id || $user->hasRole('admin');
    }
}
