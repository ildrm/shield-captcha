<?php

namespace App\Shield;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteKeys
{
    public function issue(Site $site, string $scope): string
    {
        return DB::transaction(function () use ($site, $scope): string {
            [$organization, $locked] = $this->lockSite($site);
            $this->assertIssuable($organization, $locked);

            return $this->createCredential($locked, $scope);
        }, 3);
    }

    public function revoke(Site $site, int $credentialId): void
    {
        DB::transaction(function () use ($site, $credentialId): void {
            [, $locked] = $this->lockSite($site);
            $credential = $this->lockCredential($locked, $credentialId);

            if ($credential->revoked_at === null) {
                $credential->update(['revoked_at' => now()]);
            }
        }, 3);
    }

    public function rotate(Site $site, int $credentialId): string
    {
        return DB::transaction(function () use ($site, $credentialId): string {
            [$organization, $locked] = $this->lockSite($site);
            $this->assertIssuable($organization, $locked);
            $credential = $this->lockCredential($locked, $credentialId);

            if ($credential->revoked_at !== null) {
                throw ValidationException::withMessages(['credential' => __('This credential has already been revoked.')]);
            }

            $credential->update(['revoked_at' => now()]);

            return $this->createCredential($locked, $credential->scope);
        }, 3);
    }

    /** @return array{Organization, Site} */
    private function lockSite(Site $site): array
    {
        $organization = Organization::query()->lockForUpdate()->findOrFail($site->organization_id);
        $locked = Site::query()->where('organization_id', $site->organization_id)->lockForUpdate()->findOrFail($site->getKey());

        return [$organization, $locked];
    }

    private function lockCredential(Site $site, int $id): SiteCredential
    {
        return SiteCredential::query()->where('site_id', $site->getKey())->lockForUpdate()->findOrFail($id);
    }

    private function assertIssuable(Organization $organization, Site $site): void
    {
        if (! $organization->is_active || ! $site->is_active || ($site->environment === 'production' && $site->domain_verified_at === null)) {
            throw ValidationException::withMessages(['credential' => __('Activate the site and verify a production origin before issuing credentials.')]);
        }
    }

    private function createCredential(Site $site, string $scope): string
    {
        if (! in_array($scope, ['verify', 'assess', 'sms_send', 'protect', 'automation'], true)) {
            throw ValidationException::withMessages(['scope' => __('Choose a supported credential scope.')]);
        }
        if ($scope === 'sms_send' && $site->environment !== 'production') {
            throw ValidationException::withMessages(['scope' => __('SMS delivery requires a production site.')]);
        }

        $active = SiteCredential::query()->where('site_id', $site->getKey())->where('scope', $scope)->whereNull('revoked_at')
            ->lockForUpdate()->pluck('id')->count();

        if ($active >= 10) {
            throw ValidationException::withMessages(['scope' => __('Revoke an existing credential before issuing another.')]);
        }

        $secret = 'sk_'.$scope.'_'.bin2hex(random_bytes(32));

        SiteCredential::query()->create([
            'site_id' => $site->getKey(),
            'scope' => $scope,
            'secret_digest' => hash('sha256', $secret),
            'prefix' => substr($secret, 0, 20),
        ]);

        return $secret;
    }
}
