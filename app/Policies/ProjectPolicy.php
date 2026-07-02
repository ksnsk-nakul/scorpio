<?php
namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    private function ownsWorkspace(User $user, Project $project): bool
    {
        return $user->workspaces()->where('id', $project->workspace_id)->exists()
            || $user->hasRole('admin');
    }

    public function view(User $user, Project $project): bool   { return $this->ownsWorkspace($user, $project); }
    public function update(User $user, Project $project): bool { return $this->ownsWorkspace($user, $project); }
    public function delete(User $user, Project $project): bool { return $this->ownsWorkspace($user, $project); }
}
