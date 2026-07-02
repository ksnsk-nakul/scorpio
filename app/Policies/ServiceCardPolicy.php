<?php
namespace App\Policies;

use App\Models\ServiceCard;
use App\Models\User;

class ServiceCardPolicy
{
    public function update(User $user, ServiceCard $serviceCard): bool
    {
        return $user->id === $serviceCard->user_id || $user->hasRole('admin');
    }
    public function delete(User $user, ServiceCard $serviceCard): bool
    {
        return $user->id === $serviceCard->user_id || $user->hasRole('admin');
    }
}
