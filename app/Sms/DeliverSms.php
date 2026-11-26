<?php

namespace App\Sms;

use App\Models\Site;
use App\Models\SmsDelivery;
use App\Settings\ProviderSelection;
use App\Shield\ApiProblem;
use App\Shield\Captcha;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonException;
use SensitiveParameter;

final class DeliverSms
{
    public const DESTINATION = '/\A(?:\+[1-9][0-9]{1,14}|(?:0|0098)9[0-9]{9})\z/';

    private const IRAN_MOBILE = '/\A(?:0|\+98|0098)(9[0-9]{9})\z/';

    public function __construct(
        private readonly Captcha $captcha,
        private readonly ProviderSelection $providers,
        private readonly Kavenegar $kavenegar,
        private readonly Twilio $twilio,
    ) {}

    public function send(
        #[SensitiveParameter] string $secret,
        string $key,
        string $action,
        string $eventId,
        #[SensitiveParameter] string $destination,
        #[SensitiveParameter] string $otp,
    ): array {
        if (! preg_match(self::DESTINATION, $destination)) {
            throw new ApiProblem('invalid_request', 422);
        }

        // Preserve the receipt and velocity hashes used for Iranian numbers by legacy deliveries.
        $iranianMobile = preg_match(self::IRAN_MOBILE, $destination, $matches) === 1;
        $destination = $iranianMobile ? '0'.$matches[1] : $destination;
        [$delivery, $providerSettings] = $this->captcha->assessSmsForDelivery(
            $secret,
            $key,
            $action,
            fn (Site $site, Closure $assess): array|ApiProblem => $this->claim($site, $action, $eventId, $destination, $otp, $iranianMobile, $assess),
        );

        if ($providerSettings !== null) {
            // The dispatch claim is committed before calling the carrier. An exception or timeout never reopens it.
            $result = match ($delivery->provider) {
                'kavenegar' => $this->kavenegar->send($providerSettings['api_key'], $providerSettings['template'], $destination, $otp),
                'twilio' => $this->twilio->send($providerSettings, $iranianMobile ? '+98'.substr($destination, 1) : $destination, $otp),
            };
            $delivery->update($result);
        }

        return $this->outcome($delivery);
    }

    private function claim(
        Site $site,
        string $action,
        string $eventId,
        #[SensitiveParameter] string $destination,
        #[SensitiveParameter] string $otp,
        bool $iranianMobile,
        Closure $assess,
    ): array|ApiProblem {
        $provider = $this->providers->smsProvider();
        $encryptedKey = DB::table(SmsSiteCredentials::DIGEST_KEY_TABLE)->where('organization_id', $site->organization_id)->value('digest_key');
        if (! is_string($encryptedKey)) {
            throw new ApiProblem($provider === null ? 'sms_delivery_disabled' : 'sms_provider_unavailable', 503);
        }

        $digestKey = $this->decrypt($encryptedKey);
        $eventContext = $site->id.':'.$eventId;
        $eventDigest = hash_hmac('sha256', 'shield:sms:delivery:v1:'.$eventContext, $digestKey);
        $requestDigest = hash_hmac('sha256', 'shield:sms:body:v1:'.$action.':'.$destination.':'.$otp, $digestKey);
        $existing = SmsDelivery::query()->where('site_id', $site->id)->where('event_digest', $eventDigest)->first();

        if ($existing !== null) {
            if (! hash_equals($existing->request_digest, $requestDigest)) {
                throw new ApiProblem('idempotency_conflict', 409);
            }

            return [$existing, null];
        }

        if ($provider === null) {
            throw new ApiProblem('sms_delivery_disabled', 503);
        }
        if ($provider === 'kavenegar' && ! $iranianMobile) {
            throw new ApiProblem('invalid_request', 422);
        }

        $settings = $this->configuration($site, $action, $provider);
        $assessment = $assess(
            hash_hmac('sha256', 'shield:sms:assessment:v1:'.$eventContext, $digestKey),
            hash_hmac('sha256', 'shield:sms:destination:v1:'.$destination, $digestKey),
        );
        if ($assessment instanceof ApiProblem) {
            return $assessment;
        }
        if ($assessment['decision'] !== 'allow') {
            return new ApiProblem('sms_throttled', 429, $assessment['retry_after'] ?? null);
        }

        $delivery = SmsDelivery::query()->create([
            'site_id' => $site->id,
            'action' => $action,
            'assessment_id' => $assessment['assessment_id'],
            'event_digest' => $eventDigest,
            'request_digest' => $requestDigest,
            'state' => SmsDelivery::DISPATCHING,
            'provider' => $provider,
        ]);

        return [$delivery, $settings];
    }

    private function configuration(Site $site, string $action, string $provider): array
    {
        $table = match ($provider) {
            'kavenegar' => KavenegarSettings::SETTINGS_TABLE,
            'twilio' => TwilioSettings::SETTINGS_TABLE,
            default => throw new ApiProblem('sms_delivery_disabled', 503),
        };
        $settings = DB::table($table)->where('site_id', $site->id)->first();
        if ($settings === null) {
            throw new ApiProblem('sms_provider_unavailable', 503);
        }
        if ($settings->action !== $action) {
            throw new ApiProblem('context_mismatch', 403);
        }

        if ($provider === 'kavenegar') {
            if (! KavenegarSettings::templateAllowed($settings->template)) {
                throw new ApiProblem('sms_provider_unavailable', 503);
            }

            return ['api_key' => $this->decrypt($settings->api_key), 'template' => $settings->template];
        }

        try {
            $credentials = json_decode($this->decrypt($settings->credentials), true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiProblem('sms_provider_unavailable', 503);
        }

        foreach (['account_sid', 'messaging_service_sid', 'api_key_sid', 'api_key_secret'] as $field) {
            if (! is_string($credentials[$field] ?? null) || $credentials[$field] === '') {
                throw new ApiProblem('sms_provider_unavailable', 503);
            }
        }

        return $credentials;
    }

    private function decrypt(#[SensitiveParameter] string $value): string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            throw new ApiProblem('sms_provider_unavailable', 503);
        }
    }

    private function outcome(SmsDelivery $delivery): array
    {
        if ($delivery->state === SmsDelivery::ACCEPTED) {
            return [
                'success' => true,
                'state' => SmsDelivery::ACCEPTED,
                'delivery_id' => $delivery->id,
                'provider' => $delivery->provider,
                'provider_reference' => $delivery->provider_reference,
            ];
        }

        if ($delivery->state === SmsDelivery::REJECTED) {
            throw new ApiProblem('sms_provider_rejected', 502);
        }

        if ($delivery->state === SmsDelivery::DISPATCHING
            && $delivery->created_at->isAfter(now()->subSeconds(SmsDelivery::DISPATCH_TIMEOUT_SECONDS))) {
            throw new ApiProblem('sms_dispatch_in_progress', 409);
        }

        throw new ApiProblem('sms_delivery_unknown', 409);
    }
}
