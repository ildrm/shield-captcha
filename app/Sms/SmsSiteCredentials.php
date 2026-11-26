<?php

namespace App\Sms;

use App\Models\Organization;
use App\Models\Site;
use App\Models\SiteCredential;
use App\Shield\SiteKeys;
use Closure;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SmsSiteCredentials
{
    public const DIGEST_KEY_TABLE = 'shield_sms_tenant_keys';

    public function __construct(private readonly SiteKeys $keys) {}

    public function forSite(Site $site, Closure $operation, ?string $action = null): mixed
    {
        return DB::transaction(function () use ($site, $operation, $action): mixed {
            $organization = Organization::query()->lockForUpdate()->findOrFail($site->organization_id);
            $site = Site::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($site->id);

            if ($action !== null && (! $organization->is_active || ! $site->is_active || $site->environment !== 'production'
                || $site->domain_verified_at === null || ! in_array($action, $site->actions, true))) {
                throw ValidationException::withMessages(['site' => 'Use an active, DNS-verified production site and registered action.']);
            }

            return $operation($site);
        }, 3);
    }

    public function issueIfNeeded(Site $site, bool $rotate = false): ?string
    {
        if (! DB::table(self::DIGEST_KEY_TABLE)->where('organization_id', $site->organization_id)->exists()) {
            DB::table(self::DIGEST_KEY_TABLE)->insert([
                'organization_id' => $site->organization_id,
                'digest_key' => Crypt::encryptString(bin2hex(random_bytes(32))),
            ]);
        }

        $active = SiteCredential::query()->where('site_id', $site->id)->where('scope', 'sms_send')->whereNull('revoked_at');
        if ($rotate) {
            $active->update(['revoked_at' => now()]);
        } elseif ($active->exists()) {
            return null;
        }

        return $this->keys->issue($site, 'sms_send');
    }

    public function revokeIfUnused(Site $site): bool
    {
        foreach ([KavenegarSettings::SETTINGS_TABLE, TwilioSettings::SETTINGS_TABLE] as $table) {
            if (DB::table($table)->where('site_id', $site->id)->exists()) {
                return false;
            }
        }

        SiteCredential::query()->where('site_id', $site->id)->where('scope', 'sms_send')
            ->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return true;
    }
}
