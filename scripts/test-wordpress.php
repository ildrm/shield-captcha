<?php

declare(strict_types=1);

// Run with wp eval-file against a disposable WordPress install and the built plugin.
use function ShieldCaptcha\WordPress\actions;
use function ShieldCaptcha\WordPress\configuration_valid;
use function ShieldCaptcha\WordPress\widget;

const HTTPS_FIXTURE = 'https://shield.example.test';
const WORDPRESS_FIXTURE = 'https://wordpress.example.test';

$assert = static function (bool $fact, string $message): void {
    if (! $fact) {
        throw new RuntimeException($message);
    }
};
$assert(configuration_valid(), 'Fixture credentials failed validation.');
update_option(ShieldCaptcha\WordPress\OPTION, array_keys(actions()));
$assert(str_contains(widget('wp_login'), 'data-action="wp_login"'), 'Native login widget missing.');
$assert(! str_contains(widget('wp_login'), SHIELD_CAPTCHA_VERIFY_SECRET), 'Secret appeared in markup.');
$_SERVER['SCRIPT_NAME'] = '/wp-comments-post.php';
$token = str_repeat('f', 64);
$lastAction = '';
$consumed = [];
$offline = false;

add_filter('pre_http_request', static function ($attempt, array $args, string $url) use ($token, &$lastAction, &$consumed, &$offline, $assert) {
    $assert($url === HTTPS_FIXTURE . '/api/v1/verify', 'Wrong verification endpoint.');
    $assert(($args['headers']['Authorization'] ?? '') === 'Bearer ' . SHIELD_CAPTCHA_VERIFY_SECRET, 'Missing scoped authorization.');
    $assert($args['reject_unsafe_urls'] === true && $args['redirection'] === 0, 'Unsafe outbound options.');
    if ($offline) {
        return new WP_Error('http_request_failed', 'Fixture outage');
    }
    $request = json_decode($args['body'], true, 12, JSON_THROW_ON_ERROR);
    $assert($request['site_key'] === SHIELD_CAPTCHA_SITE_KEY, 'Wrong site.');
    $assert($request['expected_origin'] === WORDPRESS_FIXTURE, 'Wrong expected origin.');
    $assert($request['expected_environment'] === 'production', 'Wrong expected environment.');
    $lastAction = $request['expected_action'];
    if ($request['token'] !== $token || in_array($lastAction, $consumed, true)) {
        return ['response' => ['code' => 422], 'body' => '{"success":false}'];
    }
    $consumed[] = $lastAction;

    return [
        'response' => ['code' => 200],
        'body' => wp_json_encode([
            'success' => true,
            'decision' => 'allow',
            'site_key' => SHIELD_CAPTCHA_SITE_KEY,
            'action' => $lastAction,
            'environment' => 'production',
            'origin' => WORDPRESS_FIXTURE,
        ]),
    ];
}, 10, 3);

$admin = get_user_by('login', 'shield-fixture');
$assert($admin instanceof WP_User, 'Test user is missing.');
do_action('login_form_login');
$assert(is_wp_error(apply_filters('authenticate', $admin, 'shield-fixture', 'fixture')), 'Native login accepted missing token.');
$_POST['shield-captcha-response'] = $token;
$result = apply_filters('authenticate', $admin, 'shield-fixture', 'fixture');
$assert($result instanceof WP_User && $lastAction === 'wp_login', 'Native login did not enforce its action.');
$assert(is_wp_error(apply_filters('authenticate', $admin, 'shield-fixture', 'fixture')), 'Native login accepted replay.');

do_action('login_form_register');
$assert(! apply_filters('registration_errors', new WP_Error(), 'fixture', 'fixture@example.test')->has_errors()
    && $lastAction === 'wp_register', 'Native registration did not enforce its action.');
do_action('login_form_lostpassword');
$errors = new WP_Error();
do_action('lostpassword_post', $errors, $admin);
$assert(! $errors->has_errors() && $lastAction === 'wp_password_reset', 'Native password reset did not enforce its action.');
$assert(apply_filters('pre_comment_approved', 1, []) === 1 && $lastAction === 'wp_comment', 'Native comments did not verify.');
$assert(is_wp_error(apply_filters('pre_comment_approved', 1, [])), 'Native comments accepted replay.');
$assert(! apply_filters('woocommerce_process_login_errors', new WP_Error())->has_errors()
    && $lastAction === 'wc_login', 'WooCommerce login did not enforce its action.');
$offline = true;
$assert(apply_filters('woocommerce_process_registration_errors', new WP_Error())->has_errors(), 'WooCommerce accepted a verifier outage.');
$offline = false;
$assert(! apply_filters('woocommerce_process_registration_errors', new WP_Error())->has_errors()
    && $lastAction === 'wc_register', 'WooCommerce registration did not enforce its action.');

echo 'Built plugin passed WordPress and WooCommerce hook contracts with a mocked verifier.' . PHP_EOL;
