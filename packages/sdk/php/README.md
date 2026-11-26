# Shield CAPTCHA verification SDK

The 0.1.0 package supports PHP 8.3 or later with cURL and Shield CAPTCHA API v1. Install from the provider's download archive or use Composer with a local artifact repository.

```bash
composer config repositories.shield artifact /path/to/downloads/sdk/php
composer require shield-captcha/verify:0.1.0
```

Keep the verification secret and expected context in server configuration. This example checks the token before the application sends its response or performs its protected operation.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

$token = $_POST['shield-captcha-response'] ?? '';
$verifier = new \ShieldCaptcha\Verifier(
    (string) getenv('SHIELD_API_ORIGIN'),
    (string) getenv('SHIELD_VERIFY_SECRET'),
);
if (! is_string($token) || ! $verifier->verify(
    $token,
    (string) getenv('SHIELD_SITE_KEY'),
    'contact',
    (string) getenv('SHIELD_SITE_ORIGIN'),
    'production',
)) {
    http_response_code(422);
    exit('Complete a fresh security verification and try again.');
}
// Perform the protected operation after your application's normal validation.
```

Use `sandbox` and a matching test key for local fixtures. Use a configured origin in serialized form, such as `https://shop.example.test` (no trailing slash). Always preserve your application's CSRF, authorization, input validation and idempotency requirements. After failure or an ambiguous network response, obtain a fresh token. The SDK performs one attempt, uses a three-second total timeout, and requires a successful allow response with the expected site, action, origin and environment.
