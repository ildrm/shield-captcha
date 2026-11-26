<?php

namespace App\Tenancy;

use App\Models\Organization;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiteManagement
{
    public function create(User $user, Organization $organization, array $fields): Site
    {
        return DB::transaction(function () use ($user, $organization, $fields): Site {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            Gate::forUser($user)->authorize('createIn', [Site::class, $organization]);

            $data = Validator::make($fields, $this->editableRules() + [
                'environment' => ['required', Rule::in(['sandbox', 'production'])],
                'origin' => ['required', 'string', 'max:300'],
            ])->validate();

            $production = $data['environment'] === 'production';
            $site = $organization->sites()->create([
                'name' => $data['name'],
                'public_key' => ($production ? 'site_prod_' : 'site_test_').bin2hex(random_bytes(16)),
                'environment' => $data['environment'],
                'origin' => Origin::normalize($data['origin'], $data['environment']),
                'domain_verification_token' => $production ? 'shield='.bin2hex(random_bytes(32)) : null,
                'actions' => array_values($data['actions']),
                'challenge_mode' => $data['challenge_mode'] ?? 'checkbox',
                'is_active' => $data['is_active'],
            ]);

            Audit::record($user, 'site.created', $organization->id, $site->id);

            return $site;
        });
    }

    public function update(User $user, Site $site, array $fields): Site
    {
        return DB::transaction(function () use ($user, $site, $fields): Site {
            $site = $this->lock($site);
            Gate::forUser($user)->authorize('update', $site);
            $data = Validator::make($fields, $this->editableRules())->validate();
            $site->update($data);
            Audit::record($user, 'site.updated', $site->organization_id, $site->id);

            return $site;
        });
    }

    public function verifyOrigin(User $user, Site $site, DnsProof $dns): bool
    {
        Gate::forUser($user)->authorize('update', $site);
        abort_unless($site->environment === 'production' && $site->domain_verification_token, 404);
        $name = Origin::dnsName($site->origin);
        $token = $site->domain_verification_token;
        if (RateLimiter::increment('shield-dns:'.$site->id, 30) !== 1 || ! $dns->matches($name, $token)) {
            return false;
        }

        return DB::transaction(function () use ($user, $site, $name, $token): bool {
            $site = $this->lock($site);
            Gate::forUser($user)->authorize('update', $site);
            if ($site->domain_verification_token !== $token || Origin::dnsName($site->origin) !== $name) {
                return false;
            }

            if ($site->domain_verified_at === null) {
                $site->update(['domain_verified_at' => now()]);
                Audit::record($user, 'site.origin_verified', $site->organization_id, $site->id);
            }

            return true;
        });
    }

    public function setOrganizationStatus(User $user, Organization $organization, bool $active, string $reason): void
    {
        abort_unless($user->is_operator && $user->hasVerifiedEmail(), 403);
        if ($reason === '' || mb_strlen($reason) > 200) {
            throw ValidationException::withMessages(['reason' => 'Enter a reason up to 200 characters.']);
        }

        DB::transaction(function () use ($user, $organization, $active, $reason): void {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organization->id);
            if ($organization->is_active !== $active) {
                $organization->update(['is_active' => $active]);
                Audit::record($user, $active ? 'organization.reactivated' : 'organization.suspended', $organization->id, reason: $reason);
            }
        });
    }

    public function lock(Site $site): Site
    {
        Organization::query()->lockForUpdate()->findOrFail($site->organization_id);

        return Site::query()->lockForUpdate()->findOrFail($site->id);
    }

    private function editableRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'actions' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'actions.*' => ['required', 'string', 'max:64', 'regex:/\A[a-z][a-z0-9_-]*\z/', 'distinct:strict'],
            'challenge_mode' => ['sometimes', 'required', Rule::in(array_keys(Site::CHALLENGE_MODES))],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
