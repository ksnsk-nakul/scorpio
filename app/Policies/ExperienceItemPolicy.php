<?php
namespace App\Policies;

use App\Models\ExperienceItem;
use App\Models\User;

class ExperienceItemPolicy
{
    public function update(User $user, ExperienceItem $experienceItem): bool
    {
        return $user->id === $experienceItem->user_id || $user->hasRole('admin');
    }
    public function delete(User $user, ExperienceItem $experienceItem): bool
    {
        return $user->id === $experienceItem->user_id || $user->hasRole('admin');
    }
}
