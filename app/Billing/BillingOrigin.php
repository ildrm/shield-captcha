<?php

namespace App\Billing;

final class BillingOrigin
{
    public static function callbackBase(?string $environment): ?string
    {
        $url = config('app.url');
        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (! isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $https = $parts['scheme'] === 'https';
        $localSandbox = $environment === 'sandbox' && $parts['scheme'] === 'http'
            && in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true);

        return $https || $localSandbox ? rtrim($url, '/') : null;
    }
}
