#!/bin/sh
set -eu

: "${SHIELD_WP_CORE_ARCHIVE:?Set a trusted local WordPress tar.gz archive.}"
: "${SHIELD_WC_ARCHIVE:?Set a trusted local WooCommerce ZIP archive.}"
: "${SHIELD_WP_DB_SOCKET:?Set a private test database socket.}"
: "${SHIELD_WP_DB_NAME:?Set an empty database beginning with shield_test_.}"

case "$SHIELD_WP_DB_NAME" in
    shield_test_*) ;;
    *) echo 'Use an empty shield_test_ database.' >&2; exit 1 ;;
esac

root=$(CDPATH='' cd -- "$(dirname "$0")/.." && pwd)
temp=$(mktemp -d "${TMPDIR:-/tmp}/shield-wordpress.XXXXXXXX")
trap 'rm -rf "$temp"' EXIT HUP INT TERM
prefix="shield_$(basename "$temp" | tr -cd '[:alnum:]')_"
tar -xzf "$SHIELD_WP_CORE_ARCHIVE" -C "$temp"
site="$temp/wordpress"
export WP_CLI_CACHE_DIR="$temp/cache"
wp_command=$(command -v wp)
wp() {
    php -d 'error_reporting=22527' "$wp_command" "--path=$site" --quiet "$@"
}

wp config create --skip-check --skip-salts --dbname="$SHIELD_WP_DB_NAME" --dbuser="$(id -un)" --dbpass='' --dbhost="localhost:$SHIELD_WP_DB_SOCKET" --dbprefix="$prefix"
wp config set WP_HTTP_BLOCK_EXTERNAL true --raw
wp config set WP_AUTO_UPDATE_CORE false --raw
wp config set DISABLE_WP_CRON true --raw
wp config set SHIELD_CAPTCHA_API_ORIGIN 'https://shield.example.test'
wp config set SHIELD_CAPTCHA_SITE_KEY 'site_prod_11111111111111111111111111111111'
wp config set SHIELD_CAPTCHA_VERIFY_SECRET 'sk_verify_2222222222222222222222222222222222222222222222222222222222222222'
wp core install --url='https://wordpress.example.test' --title='Shield fixture' --skip-email --admin_user='shield-fixture' --admin_email='fixture@example.test' --admin_password='isolated-fixture-only'
unzip -q "$SHIELD_WC_ARCHIVE" -d "$site/wp-content/plugins"
unzip -q "$root/downloads/cms/wordpress/shield-captcha-wordpress-0.2.0.zip" -d "$site/wp-content/plugins"
wp plugin activate woocommerce shield-captcha
wp core version
wp plugin get woocommerce --field=version
wp eval-file "$root/scripts/test-wordpress.php"
