<?php

namespace App\Tenancy;

use App\Models\Site;
use App\Models\User;
use App\Shield\SiteKeys;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CredentialDisplay
{
    public function change(
        User $user,
        Site $site,
        string $sessionId,
        string $operation,
        string|int $arg,
    ): ?string {
        return DB::transaction(function () use ($user, $site, $sessionId, $operation, $arg): ?string {
            $site = app(SiteManagement::class)->lock($site);
            Gate::forUser($user)->authorize('manageCredentials', $site);

            $keys = app(SiteKeys::class);
            $key = match ($operation) {
                'issued' => $keys->issue($site, (string) $arg),
                'rotated' => $keys->rotate($site, (int) $arg),
                'revoked' => $keys->revoke($site, (int) $arg),
            };
            Audit::record($user, 'credential.'.$operation, $site->organization_id, $site->id);

            if ($key === null) {
                return null;
            }

            $receipt = bin2hex(random_bytes(32));
            DB::table('shield_key_displays')->insert([
                'id' => hash('sha256', $receipt),
                'user_id' => $user->id,
                'site_id' => $site->id,
                'session_digest' => hash('sha256', $sessionId),
                'encrypted_key' => Crypt::encryptString($key),
                'expires_at' => now()->addMinutes(2),
            ]);

            return $receipt;
        });
    }

    public function consume(User $user, Site $site, string $sessionId, string $receipt): ?string
    {
        return DB::transaction(function () use ($user, $site, $sessionId, $receipt): ?string {
            $site = app(SiteManagement::class)->lock($site);
            Gate::forUser($user)->authorize('manageCredentials', $site);

            $row = DB::table('shield_key_displays')
                ->where('id', hash('sha256', $receipt))
                ->where('site_id', $site->id)
                ->where('user_id', $user->id)
                ->where('session_digest', hash('sha256', $sessionId))
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }

            DB::table('shield_key_displays')->where('id', $row->id)->delete();

            return Crypt::decryptString($row->encrypted_key);
        });
    }
}
