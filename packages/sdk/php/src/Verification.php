<?php

declare(strict_types=1);

namespace ShieldCaptcha;

final class Verification
{
    public static function request(
        string $token,
        string $siteKey,
        string $action,
        string $origin,
        string $environment,
    ): ?array {
        $prefix = match ($environment) {
            'production' => 'site_prod_',
            'sandbox' => 'site_test_',
            default => null,
        };

        if (
            ! preg_match('/\A[a-f0-9]{64}\z/', $token)
            || $prefix === null
            || ! preg_match('/\A' . $prefix . '[a-f0-9]{32}\z/', $siteKey)
            || ! preg_match('/\A[a-z][a-z0-9_.:-]{0,63}\z/', $action)
            || ! self::isOrigin($origin, $environment === 'sandbox')
        ) {
            return null;
        }

        return [
            'token' => $token,
            'site_key' => $siteKey,
            'expected_action' => $action,
            'expected_origin' => $origin,
            'expected_environment' => $environment,
        ];
    }

    public static function passed(int $status, string $body, array $request): bool
    {
        if ($status !== 200 || strlen($body) > 16384) {
            return false;
        }

        $response = json_decode($body, true, 12);

        return is_array($response)
            && ($response['success'] ?? null) === true
            && ($response['decision'] ?? null) === 'allow'
            && ($response['site_key'] ?? null) === $request['site_key']
            && ($response['action'] ?? null) === $request['expected_action']
            && ($response['origin'] ?? null) === $request['expected_origin']
            && ($response['environment'] ?? null) === $request['expected_environment'];
    }

    public static function isOrigin(string $origin, bool $allowLoopback = false): bool
    {
        if (! preg_match('~\A(https?)://([a-z0-9.-]+|\[::1\])(?::([1-9][0-9]{0,4}))?\z~', $origin, $parts)) {
            return false;
        }
        [, $scheme, $host] = $parts;
        $port = isset($parts[3]) ? (int) $parts[3] : null;
        if ($port > 65535 || $port === ($scheme === 'https' ? 443 : 80) || str_ends_with($host, '.')) {
            return false;
        }
        $loopback = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
        if ($loopback) {
            return $allowLoopback;
        }

        return $scheme === 'https'
            && str_contains($host, '.')
            && filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
