<?php
namespace App\Policies;

use App\Models\ContactSubmission;
use App\Models\User;

class ContactSubmissionPolicy
{
    public function update(User $user, ContactSubmission $contactSubmission): bool
    {
        return $user->id === $contactSubmission->user_id || $user->hasRole('admin');
    }
}
