<?php

namespace App\Sms;

use App\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

final class TwilioSettings
{
    public const SETTINGS_TABLE = 'shield_sms_twilio_sites';

    public function __construct(private readonly SmsSiteCredentials $siteCredentials) {}

    public function configure(
        Site $site,
        string $action,
        string $accountSid,
        string $messagingServiceSid,
        #[SensitiveParameter] string $apiKeySid,
        #[SensitiveParameter] string $apiKeySecret,
        bool $rotateSendKey = false,
    ): ?string {
        if (! preg_match('/\AAC[0-9a-fA-F]{32}\z/', $accountSid)
            || ! preg_match('/\AMG[0-9a-fA-F]{32}\z/', $messagingServiceSid)
            || ! preg_match('/\ASK[0-9a-fA-F]{32}\z/', $apiKeySid)
            || ! preg_match('/\A[!-~]{16,256}\z/', $apiKeySecret)) {
            throw ValidationException::withMessages(['provider' => 'Enter valid Twilio account, messaging service, and API key credentials.']);
        }

        $credentials = Crypt::encryptString(json_encode([
            'account_sid' => $accountSid,
            'messaging_service_sid' => $messagingServiceSid,
            'api_key_sid' => $apiKeySid,
            'api_key_secret' => $apiKeySecret,
        ], JSON_THROW_ON_ERROR));

        return $this->siteCredentials->forSite($site, function (Site $site) use ($action, $credentials, $rotateSendKey): ?string {
            DB::table(self::SETTINGS_TABLE)->upsert([[
                'site_id' => $site->id,
                'action' => $action,
                'credentials' => $credentials,
                'created_at' => now(),
                'updated_at' => now(),
            ]], ['site_id'], ['action', 'credentials', 'updated_at']);

            return $this->siteCredentials->issueIfNeeded($site, $rotateSendKey);
        }, $action);
    }

    public function disable(Site $site): bool
    {
        return $this->siteCredentials->forSite($site, function (Site $site): bool {
            DB::table(self::SETTINGS_TABLE)->where('site_id', $site->id)->delete();

            return $this->siteCredentials->revokeIfUnused($site);
        });
    }
}
