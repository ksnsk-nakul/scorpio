<?php
namespace App\Policies;

use App\Models\Skill;
use App\Models\User;

class SkillPolicy
{
    public function update(User $user, Skill $skill): bool { return $user->id === $skill->user_id || $user->hasRole('admin'); }
    public function delete(User $user, Skill $skill): bool { return $user->id === $skill->user_id || $user->hasRole('admin'); }
}
