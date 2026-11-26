<?php

namespace App\Tenancy;

use Illuminate\Validation\ValidationException;

class Origin
{
    public static function normalize(string $origin, string $environment): string
    {
        $parts = parse_url(trim($origin));
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(rtrim($parts['host'] ?? '', '.'));
        $port = $parts['port'] ?? null;

        if (! $parts || ! in_array($scheme, ['http', 'https'], true) || $host === ''
            || array_diff(array_keys($parts), ['scheme', 'host', 'port', 'path']) !== []
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || ($port !== null && ($port < 1 || $port > 65535))) {
            self::reject();
        }

        $ip = trim($host, '[]');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            if ($environment === 'production') {
                self::reject();
            }

            $canonicalIp = inet_ntop(inet_pton($ip));
            $host = str_contains($canonicalIp, ':') ? '['.$canonicalIp.']' : $canonicalIp;
        } else {
            $host = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_USE_STD3_RULES, INTL_IDNA_VARIANT_UTS46);
            if (! $host || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
                || strlen($host) > 240
                || preg_match('/(?:^|\.)[0-9]+$/D', $host)) {
                self::reject();
            }

            if ($environment === 'production' && (! str_contains($host, '.')
                || preg_match('/\.(test|example|invalid|localhost|local|onion|internal|home|arpa)$/D', $host))) {
                self::reject();
            }
        }

        if ($environment === 'production' && $scheme !== 'https') {
            self::reject();
        }

        $nonDefaultPort = $port !== null && $port !== ($scheme === 'https' ? 443 : 80);

        $serialized = $scheme.'://'.$host.($nonDefaultPort ? ':'.$port : '');
        if (strlen($serialized) > 255) {
            self::reject();
        }

        return $serialized;
    }

    public static function dnsName(string $origin): string
    {
        return '_shield-captcha.'.parse_url($origin, PHP_URL_HOST);
    }

    private static function reject(): never
    {
        throw ValidationException::withMessages([
            'origin' => 'Enter a single browser origin with no path or credentials. Production requires HTTPS and a public DNS hostname.',
        ]);
    }
}
