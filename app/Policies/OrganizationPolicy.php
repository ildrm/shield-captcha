<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_operator && $user->hasVerifiedEmail();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->viewAny($user);
    }
}
