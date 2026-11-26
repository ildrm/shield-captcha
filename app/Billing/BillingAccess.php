<?php

namespace App\Billing;

use App\Models\Organization;
use App\Models\User;

final class BillingAccess
{
    public static function canManage(User $user, Organization $organization): bool
    {
        return ! $user->is_operator
            && $user->hasVerifiedEmail()
            && in_array($user->roleInOrganization($organization->id), ['owner', 'admin', 'billing_manager'], true);
    }
}
