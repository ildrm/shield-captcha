<?php

namespace App\Sms;

use App\Models\SmsDelivery;
use App\Support\ProviderJson;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Throwable;

final class Twilio
{
    /** @return array{state: string, provider_reference: ?string} */
    public function send(
        #[SensitiveParameter] array $credentials,
        #[SensitiveParameter] string $destination,
        #[SensitiveParameter] string $otp,
    ): array {
        $unknown = ['state' => SmsDelivery::UNKNOWN, 'provider_reference' => null];
        $deadline = hrtime(true) + 7_000_000_000;

        try {
            $accountSid = $credentials['account_sid'];
            $messagingService = $credentials['messaging_service_sid'];
            $response = Http::asForm()->acceptJson()->withoutRedirecting()->connectTimeout(2)->timeout(7)
                ->withBasicAuth($credentials['api_key_sid'], $credentials['api_key_secret'])
                ->withOptions(['verify' => true, 'debug' => false, 'stream' => true, 'read_timeout' => 2])
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($accountSid).'/Messages.json', [
                    'To' => $destination,
                    'MessagingServiceSid' => $messagingService,
                    'Body' => 'Your verification code is '.$otp.'.',
                    'ContentRetention' => 'discard',
                ]);
            $payload = ProviderJson::read($response, $deadline);

            if (in_array($response->status(), [400, 401, 403, 404, 422], true) && is_int($payload['code'] ?? null)) {
                return ['state' => SmsDelivery::REJECTED, 'provider_reference' => null];
            }

            $reference = $payload['sid'] ?? null;
            if ($response->status() !== 201 || ! is_string($reference) || ! preg_match('/\A(?:SM|MM)[0-9a-fA-F]{32}\z/', $reference)
                || ($payload['account_sid'] ?? null) !== $accountSid || ($payload['messaging_service_sid'] ?? null) !== $messagingService) {
                return $unknown;
            }

            $status = $payload['status'] ?? null;
            if (in_array($status, ['accepted', 'queued', 'sending', 'sent', 'delivered'], true)) {
                return ['state' => SmsDelivery::ACCEPTED, 'provider_reference' => $reference];
            }
            if (in_array($status, ['failed', 'undelivered', 'canceled'], true)) {
                return ['state' => SmsDelivery::REJECTED, 'provider_reference' => $reference];
            }
        } catch (Throwable) {
            // Provider errors may include request or response details containing personal data.
        }

        return $unknown;
    }
}
