<?php

namespace App\Sms;

use App\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class KavenegarSettings
{
    public const SETTINGS_TABLE = 'shield_sms_kavenegar_sites';

    public function __construct(private readonly SmsSiteCredentials $siteCredentials) {}

    public function configure(Site $site, string $action, string $template, #[SensitiveParameter] string $apiKey, bool $rotateSendKey = false): ?string
    {
        if (! self::templateAllowed($template) || ! preg_match('/\A[A-Za-z0-9_-]{16,256}\z/', $apiKey)) {
            throw ValidationException::withMessages(['provider' => 'Use an allowed template and a valid Kavenegar API key.']);
        }

        $encrypted = Crypt::encryptString($apiKey);

        return $this->siteCredentials->forSite($site, function (Site $site) use ($action, $template, $encrypted, $rotateSendKey): ?string {
            DB::table(self::SETTINGS_TABLE)->upsert([[
                'site_id' => $site->id,
                'action' => $action,
                'template' => $template,
                'api_key' => $encrypted,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['site_id'], ['action', 'template', 'api_key', 'updated_at']);

            $twilioConfigured = DB::table(TwilioSettings::SETTINGS_TABLE)->where('site_id', $site->id)->exists();

            return $this->siteCredentials->issueIfNeeded($site, $rotateSendKey || ! $twilioConfigured);
        }, $action);
    }

    public function disable(Site $site): bool
    {
        return $this->siteCredentials->forSite($site, function (Site $site): bool {
            DB::table(self::SETTINGS_TABLE)->where('site_id', $site->id)->delete();

            return $this->siteCredentials->revokeIfUnused($site);
        });
    }

    public static function templateAllowed(string $template): bool
    {
        return preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9-]{0,63}\z/', $template) === 1
            && in_array($template, config('sms.kavenegar.templates'), true);
    }
}
