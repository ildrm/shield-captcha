=== Shield CAPTCHA ===
Requires at least: 6.6
Requires PHP: 8.3
Stable tag: 0.2.0
License: GPL-2.0-only

Adds explicit server-side verification to selected native forms.

== Installation ==

1. Build the archives from the project root using `node scripts/build-releases.mjs`, or obtain the generated WordPress ZIP and verify its SHA-256 checksum.
2. Upload and activate the ZIP in Plugins. By default no forms are enabled.
3. Create a site in Shield for the exact public HTTPS WordPress origin. Register the actions for the forms you intend to protect. Configure test credentials on staging before switching to production credentials.
4. In wp-config.php set the server-side constants SHIELD_CAPTCHA_API_ORIGIN (HTTPS, no trailing slash), SHIELD_CAPTCHA_SITE_KEY, SHIELD_CAPTCHA_VERIFY_SECRET, and SHIELD_CAPTCHA_ENVIRONMENT (production or sandbox). Optionally set SHIELD_CAPTCHA_SITE_ORIGIN to the exact customer origin when it differs from home_url(). Protect wp-config.php as you would your database credentials.
5. In Settings → Shield CAPTCHA enable and test the individual forms. Begin with a staging site. Enable login only after you confirm your server-side recovery path.

== Supported actions ==

wp_login (native login); wp_register (native registration); wp_password_reset (native reset-email request); wp_comment (native wp-comments-post.php form); wc_login (classic WooCommerce login); wc_register (classic WooCommerce account registration). Checkout, Store API, REST/XML-RPC submissions, site builder form overrides and WordPress multisite network management require separate coverage. Check themes for form hook support.

== Outages and recovery ==

The selected forms deny operations when the service cannot verify. Authorized operators can temporarily define SHIELD_CAPTCHA_SUSPEND as true in wp-config.php or run `wp plugin deactivate shield-captcha` using trusted server access. Removing the plugin deletes its local form settings. Remove the site key and secret constants separately and revoke unused keys in Shield.

== Changelog ==

= 0.2.0 =
Updates the bundled widget. WordPress forms continue to use the visible verification button.

= 0.1.0 =
First development archive. See the release manifest for coverage.
