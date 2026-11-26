<?php
/**
 * Plugin Name: Shield CAPTCHA
 * Description: Adds server-verified Shield CAPTCHA to selected native WordPress and WooCommerce forms.
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 8.3
 * License: GPL-2.0-only
 * Text Domain: shield-captcha
 */

declare(strict_types=1);

namespace ShieldCaptcha\WordPress;

use ShieldCaptcha\Verification;
use WP_Error;
use WP_User;

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists(Verification::class)) {
    require_once __DIR__ . '/src/Verification.php';
}

const OPTION = 'shield_captcha_forms';
const VERSION = '0.2.0';

function configured(string $key): string
{
    $value = defined($key) ? constant($key) : '';

    return is_string($value) ? $value : '';
}

function environment(): string
{
    return configured('SHIELD_CAPTCHA_ENVIRONMENT') ?: 'production';
}

function api_origin(): string
{
    $origin = configured('SHIELD_CAPTCHA_API_ORIGIN');

    return Verification::isOrigin($origin) ? $origin : '';
}

function site_origin(): string
{
    $explicit = configured('SHIELD_CAPTCHA_SITE_ORIGIN');
    if ($explicit !== '') {
        return $explicit;
    }
    $parts = wp_parse_url(home_url('/'));
    if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
        return '';
    }
    $scheme = strtolower($parts['scheme']);
    $port = $parts['port'] ?? null;
    $default = $scheme === 'https' ? 443 : 80;

    return $scheme . '://' . strtolower($parts['host']) . ($port === null || $port === $default ? '' : ':' . $port);
}

function actions(): array
{
    return [
        'wp_login' => __('WordPress login', 'shield-captcha'),
        'wp_register' => __('WordPress registration', 'shield-captcha'),
        'wp_password_reset' => __('WordPress password reset request', 'shield-captcha'),
        'wp_comment' => __('WordPress native comment form', 'shield-captcha'),
        'wc_login' => __('WooCommerce classic login form', 'shield-captcha'),
        'wc_register' => __('WooCommerce classic account registration', 'shield-captcha'),
    ];
}

function selected(string $action): bool
{
    if (defined('SHIELD_CAPTCHA_SUSPEND') && constant('SHIELD_CAPTCHA_SUSPEND') === true) {
        return false;
    }
    $forms = get_option(OPTION, []);

    return is_array($forms) && in_array($action, $forms, true);
}

function configuration_valid(): bool
{
    return api_origin() !== ''
        && preg_match('/\Ask_verify_[a-f0-9]{64}\z/', configured('SHIELD_CAPTCHA_VERIFY_SECRET')) === 1
        && Verification::request(
            str_repeat('0', 64),
            configured('SHIELD_CAPTCHA_SITE_KEY'),
            'wp_login',
            site_origin(),
            environment(),
        ) !== null;
}

function verified(string $action): bool
{
    $submitted = $_POST['shield-captcha-response'] ?? null;
    if (! is_string($submitted) || ! configuration_valid()) {
        return false;
    }
    $request = Verification::request(
        wp_unslash($submitted),
        configured('SHIELD_CAPTCHA_SITE_KEY'),
        $action,
        site_origin(),
        environment(),
    );
    if ($request === null) {
        return false;
    }
    $response = wp_safe_remote_post(api_origin() . '/api/v1/verify', [
        'headers' => [
            'Authorization' => 'Bearer ' . configured('SHIELD_CAPTCHA_VERIFY_SECRET'),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode($request),
        'timeout' => 3,
        'redirection' => 0,
        'limit_response_size' => 16384,
    ]);

    return ! is_wp_error($response) && Verification::passed(
        (int) wp_remote_retrieve_response_code($response),
        (string) wp_remote_retrieve_body($response),
        $request,
    );
}

function error_message(): string
{
    return __('Complete a fresh security verification and try again.', 'shield-captcha');
}

function append_error(WP_Error $errors, string $action): WP_Error
{
    if (selected($action) && ! $errors->has_errors() && ! verified($action)) {
        $errors->add('shield_captcha_required', error_message());
    }

    return $errors;
}

function widget(string $action): string
{
    if (! selected($action)) {
        return '';
    }
    if (! configuration_valid()) {
        return '<p>' . esc_html__('Security verification is temporarily unavailable.', 'shield-captcha') . '</p>';
    }
    // Keep the browser code version in lockstep with the installed plugin.
    $locale = str_starts_with(get_locale(), 'fa') ? 'fa' : 'en';
    wp_enqueue_script(
        'shield-captcha-widget',
        plugins_url('assets/widget.js', __FILE__),
        [],
        VERSION,
        ['in_footer' => true, 'strategy' => 'defer'],
    );

    return sprintf(
        '<div data-shield-captcha data-site-key="%s" data-action="%s" data-locale="%s" data-api-origin="%s">%s</div>',
        esc_attr(configured('SHIELD_CAPTCHA_SITE_KEY')),
        esc_attr($action),
        esc_attr($locale),
        esc_attr(api_origin()),
        esc_html__('Enable JavaScript to complete verification.', 'shield-captcha'),
    );
}

add_action('login_form', static fn () => print widget('wp_login'));
add_action('register_form', static fn () => print widget('wp_register'));
add_action('lostpassword_form', static fn () => print widget('wp_password_reset'));
add_action('woocommerce_login_form', static fn () => print widget('wc_login'));
add_action('woocommerce_register_form', static fn () => print widget('wc_register'));
add_filter('comment_form_submit_field', static fn (string $field): string => widget('wp_comment') . $field);

// wp-login.php runs login_form_login before native login processing.
add_filter('authenticate', static function ($user) {
    if ($user instanceof WP_User && did_action('login_form_login') && selected('wp_login') && ! verified('wp_login')) {
        return new WP_Error('shield_captcha_required', error_message());
    }

    return $user;
}, 99);
add_filter('registration_errors', static fn (WP_Error $errors): WP_Error => did_action('login_form_register')
    ? append_error($errors, 'wp_register')
    : $errors);
add_action('lostpassword_post', static function (WP_Error $errors): void {
    if (did_action('login_form_lostpassword') || did_action('login_form_retrievepassword')) {
        append_error($errors, 'wp_password_reset');
    }
});
add_filter('pre_comment_approved', static function ($approved) {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (
        ! is_wp_error($approved) && is_string($script) && basename($script) === 'wp-comments-post.php'
        && selected('wp_comment') && ! verified('wp_comment')
    ) {
        return new WP_Error('shield_captcha_required', error_message());
    }

    return $approved;
});
add_filter('woocommerce_process_login_errors', static fn (WP_Error $errors): WP_Error => append_error($errors, 'wc_login'));
add_filter('woocommerce_process_registration_errors', static fn (WP_Error $errors): WP_Error => append_error($errors, 'wc_register'));

add_action('admin_menu', static function (): void {
    add_options_page('Shield CAPTCHA', 'Shield CAPTCHA', 'manage_options', 'shield-captcha', __NAMESPACE__ . '\\settings_page');
});
add_action('admin_init', static function (): void {
    register_setting('shield_captcha', OPTION, [
        'type' => 'array',
        'default' => [],
        'show_in_rest' => false,
        'sanitize_callback' => __NAMESPACE__ . '\\sanitize_forms',
    ]);
});

function sanitize_forms($input): array
{
    $forms = array_keys(actions());
    $selected = is_array($input) ? array_values(array_filter(
        $forms,
        static fn (string $action): bool => in_array($action, $input, true),
    )) : [];

    if ($selected !== [] && ! configuration_valid()) {
        add_settings_error(OPTION, 'shield_captcha_configuration', __('Configure valid server settings before enabling protection.', 'shield-captcha'));
        $previous = get_option(OPTION, []);

        return is_array($previous) ? $previous : [];
    }

    return $selected;
}

function settings_page(): void
{
    if (! current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Shield CAPTCHA</h1>
        <p><?php echo esc_html(configuration_valid()
            ? __('Server settings are present. Test each enabled form before rollout.', 'shield-captcha')
            : __('Set the Shield CAPTCHA constants in wp-config.php before enabling forms.', 'shield-captcha')); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('shield_captcha'); ?>
            <input type="hidden" name="<?php echo esc_attr(OPTION); ?>[]" value="">
            <fieldset>
                <legend><?php esc_html_e('Protected forms', 'shield-captcha'); ?></legend>
                <?php foreach (actions() as $action => $label) : ?>
                    <p><label>
                        <input type="checkbox" name="<?php echo esc_attr(OPTION); ?>[]" value="<?php echo esc_attr($action); ?>"
                            <?php checked(selected($action)); ?>>
                        <?php echo esc_html($label); ?>
                    </label></p>
                <?php endforeach; ?>
            </fieldset>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
