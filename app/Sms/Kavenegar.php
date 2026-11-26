<?php

namespace App\Sms;

use App\Models\SmsDelivery;
use App\Support\ProviderJson;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;
use Throwable;

final class Kavenegar
{
    /** @return array{state: string, provider_reference: ?string} */
    public function send(#[SensitiveParameter] string $apiKey, string $template, #[SensitiveParameter] string $destination, #[SensitiveParameter] string $otp): array
    {
        $unknown = ['state' => SmsDelivery::UNKNOWN, 'provider_reference' => null];
        $deadline = hrtime(true) + 7_000_000_000;

        try {
            $response = Http::asForm()->acceptJson()->withoutRedirecting()->connectTimeout(2)->timeout(7)
                ->withOptions(['verify' => true, 'debug' => false, 'stream' => true, 'read_timeout' => 2])
                ->post('https://api.kavenegar.com/v1/'.rawurlencode($apiKey).'/verify/lookup.json', [
                    'receptor' => $destination,
                    'token' => $otp,
                    'template' => $template,
                    'type' => 'sms',
                ]);
            $payload = ProviderJson::read($response, $deadline);
            $code = $payload['return']['status'] ?? null;

            if ($response->status() === $code && in_array($code, [400, 401, 402, 403, 404, 405, 406, 407, 411, 416, 418, 422, 424, 426, 428, 431, 432], true)) {
                return ['state' => SmsDelivery::REJECTED, 'provider_reference' => null];
            }

            if ($response->status() !== 200 || $code !== 200) {
                return $unknown;
            }

            $entries = $payload['entries'] ?? null;
            if (! is_array($entries) || $entries === [] || (array_is_list($entries) && count($entries) !== 1)) {
                return $unknown;
            }

            $entry = array_is_list($entries) ? $entries[0] : $entries;
            $reference = $entry['messageid'] ?? null;
            $status = $entry['status'] ?? null;

            if ((! is_int($reference) && ! is_string($reference)) || ! preg_match('/\A[1-9][0-9]{0,18}\z/', (string) $reference)) {
                return $unknown;
            }

            if (in_array($status, [1, 2, 4, 5, 10], true)) {
                return ['state' => SmsDelivery::ACCEPTED, 'provider_reference' => (string) $reference];
            }

            if (in_array($status, [6, 11, 13, 14], true)) {
                return ['state' => SmsDelivery::REJECTED, 'provider_reference' => (string) $reference];
            }
        } catch (Throwable) {
            // The transport exception can contain a credential in its URL. Never forward it to the logger.
        }

        return $unknown;
    }
}
