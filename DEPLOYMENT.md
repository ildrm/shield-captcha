# Deploy on shared PHP hosting

## Build and preflight

Use PHP 8.3+ for both the web handler and cron CLI, an InnoDB-capable MySQL/MariaDB database, HTTPS, and an SMTP service or host-supported mail transport. Use `composer check-platform-reqs --no-dev` to check the actual installed extensions. The verified local run used PHP 8.5.8 and MariaDB 13.0.2. The lockfile resolves on the PHP 8.3 platform. Verify the host's selected web and CLI versions before upload.

Build an upload ZIP from a reviewed checkout with `node scripts/build-releases.mjs`, `sh scripts/build-shared-host.sh`, and then `node scripts/build-releases.mjs` to include the new hosting bundle in the catalog. The builder installs locked production packages in a temporary staging directory, publishes Filament assets, and produces `downloads/self-hosted/shield-captcha-shared-host-0.4.0.zip`. Node is used during release assembly. The ZIP contains PHP dependencies and browser assets.

## cPanel and DirectAdmin

1. Create a dedicated subdomain, enable HTTPS, and configure its document root to the deployment's `public/` directory. Keep the remaining application files and `.env` one level above the public directory. In cPanel use **Domains** and **MultiPHP Manager**. In DirectAdmin use the domain/subdomain setup and the host's PHP selector. If the control panel locks the document root, have the host map it to `public/`, or move only the contents of `public/` into the web root and adjust all three paths in its `index.php` to the private application directory.
2. Extract the verified ZIP and create a fresh database and a dedicated account in your panel's database manager. Set least-privilege database permissions. The account used for migrations needs schema modification rights.
3. Copy `.env.example` to private `.env`. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://YOUR_SHIELD_DOMAIN`, `SESSION_SECURE_COOKIE=true`, database settings and real mail transport. Do not share the configuration file. Set permissions so the PHP account can write `bootstrap/cache` and `storage` only where needed.
4. From SSH or the panel's terminal, run the commands below. On a plan with no terminal, run these through the host's trusted PHP CLI facility or ask the host to provision the instance. Do not expose a web installer or unrestricted command runner.

   ```sh
   php artisan key:generate
   php artisan migrate --force
   php artisan shield:preflight --production
   php artisan shield:operator operator@example.org
   php artisan config:cache
   ```

   To exercise the configured delivery transport, run `php artisan shield:mail-smoke YOUR_TEST_INBOX` and confirm receipt in an inbox you control. Then test the actual customer verification email on the deployed host.

5. In **Cron Jobs**, use the path to the same PHP version selected for the site. This runs the scheduled bounded cleanup and retention tasks. Check that **Scheduled jobs** in the operator dashboard shows a recent heartbeat. Review `cron.log` for individual command failures and establish a log rotation policy.

   ```cron
   * * * * * cd /home/ACCOUNT/apps/shield && /path/to/php artisan schedule:run >> /home/ACCOUNT/apps/shield/storage/logs/cron.log 2>&1
   ```

6. Visit `/health/ready`, `/docs`, `/customer/login` and `/admin/login`. Confirm the application directory, `.env`, Composer files, and private storage have no public URL. Set a 4 KiB body limit specifically for `/api/v1/` at the host or proxy and preserve the `Authorization` header. Use the supplied Apache rewrite example for standard routing. If behind a trusted proxy, preserve real client IPs by configuring only the actual trusted proxy addresses at deployment. Otherwise shared proxy IPs share a source bucket.
7. Create a sandbox site and run the two-origin example. For production sites, complete DNS TXT verification and confirm the origin is the exact HTTPS origin in both customer code and the site settings. Verify fail-closed behavior before redirecting real traffic.
8. Deploy the database migrations before changing provider selections. Follow [provider setup](docs/providers.md) and confirm the host can reach each enabled service over HTTPS. If Stripe is enabled, set a 256 KiB request-body limit on `/billing/stripe/webhook` separately from the 4 KiB API limit. Preserve the signature header and raw body. Keep request tracing and proxy logs free of provider credentials, payment returns and SMS payloads.

## Operations and restore

Back up the database, protected storage, release files and the configuration key separately. Keep encrypted, access-controlled copies outside the web root. Restore into a fresh isolated database and new application staging directory using the same key and the matching release. Run the preflight, inspect organization isolation, sign in with an authorized operator, test sandbox CAPTCHA and SMS behavior, and resume cron. Document restore time and the date of the recovered data.

For upgrades, take a verified backup, compare migrations and release notes, enable maintenance in the target deployment, upload and switch releases, then migrate and smoke-test. Restore application and database together when reversing a non-backwards-compatible schema change. Never regenerate the original application key on upgrades or restores. Use a separate host or approved capacity window for load testing.
