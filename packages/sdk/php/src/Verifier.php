<?php

declare(strict_types=1);

namespace ShieldCaptcha;

use InvalidArgumentException;

final class Verifier
{
    public function __construct(
        private readonly string $apiOrigin,
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
        if (! Verification::isOrigin($apiOrigin, true) || ! preg_match('/\Ask_verify_[a-f0-9]{64}\z/', $secret)) {
            throw new InvalidArgumentException('Invalid Shield CAPTCHA server configuration.');
        }
    }

    public function verify(
        #[\SensitiveParameter]
        string $token,
        string $siteKey,
        string $action,
        string $origin,
        string $environment = 'production',
    ): bool {
        $request = Verification::request($token, $siteKey, $action, $origin, $environment);
        if ($request === null || ! Verification::isOrigin($this->apiOrigin, $environment === 'sandbox')) {
            return false;
        }
        $handle = curl_init($this->apiOrigin . '/api/v1/verify');
        if ($handle === false) {
            return false;
        }
        $body = '';
        try {
            if (! curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($request, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->secret,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_CONNECTTIMEOUT_MS => 1500,
                CURLOPT_TIMEOUT_MS => 3000,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 16384) {
                        return 0;
                    }
                    $body .= $chunk;

                    return strlen($chunk);
                },
            ]) || curl_exec($handle) === false) {
                return false;
            }

            return Verification::passed(curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $body, $request);
        } finally {
            unset($handle);
        }
    }
}
