<?php
namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function delete(User $user, Media $media): bool
    {
        return $user->id === $media->user_id || $user->hasRole('admin');
    }
}
