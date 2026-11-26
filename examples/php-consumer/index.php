<?php

declare(strict_types=1);

use ShieldCaptcha\Verifier;

require __DIR__.'/../../packages/sdk/php/src/Verification.php';
require __DIR__.'/../../packages/sdk/php/src/Verifier.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$api = getenv('SHIELD_API_ORIGIN') ?: '';
$origin = getenv('SHIELD_CONSUMER_ORIGIN') ?: '';
$siteKey = getenv('SHIELD_SITE_KEY') ?: '';
$secret = getenv('SHIELD_VERIFY_SECRET') ?: '';
$action = 'contact';

if ($api === '' || $origin === '' || ! str_starts_with($siteKey, 'site_test_') || $secret === '') {
    http_response_code(503);
    exit('Configure the sandbox example before starting the server.');
}

header('Cache-Control: no-store');
$result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['shield-captcha-response'] ?? '';
    $verified = is_string($token) && (new Verifier($api, $secret))->verify($token, $siteKey, $action, $origin, 'sandbox');
    http_response_code($verified ? 200 : 422);
    $result = $verified ? 'Accepted sandbox submission.' : 'Verification failed. Complete a new challenge.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shield CAPTCHA sandbox consumer</title>
    <script src="<?= escape($api) ?>/assets/captcha/v1/widget.js" defer></script>
</head>
<body>
<main>
    <h1>Protected sandbox form</h1>
    <?php if ($result !== '') { ?>
        <p role="status"><?= escape($result) ?></p>
    <?php } ?>
    <form method="post">
        <div data-shield-captcha data-site-key="<?= escape($siteKey) ?>" data-action="<?= $action ?>" data-mode="managed"></div>
        <button type="submit">Submit</button>
    </form>
    <noscript>This example requires JavaScript for the challenge.</noscript>
</main>
</body>
</html>
