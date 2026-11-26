<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Filament\Facades\Filament;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Organization && $user->canAccessTenant($tenant);
    }

    public function view(User $user, Site $site): bool
    {
        return ! $user->is_operator && $user->roleInOrganization($site->organization_id) !== null;
    }

    public function create(User $user): bool
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Organization && $this->createIn($user, $tenant);
    }

    public function createIn(User $user, Organization $organization): bool
    {
        return $this->canManage($user, $organization->getKey());
    }

    public function update(User $user, Site $site): bool
    {
        return $this->canManage($user, $site->organization_id);
    }

    public function manageCredentials(User $user, Site $site): bool
    {
        return $this->canManage($user, $site->organization_id);
    }

    public function delete(User $user, Site $site): bool
    {
        return false;
    }

    private function canManage(User $user, int $organizationId): bool
    {
        return ! $user->is_operator
            && $user->hasVerifiedEmail()
            && in_array($user->roleInOrganization($organizationId), ['owner', 'admin', 'developer'], true);
    }
}
