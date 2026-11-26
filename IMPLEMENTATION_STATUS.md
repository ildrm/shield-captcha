# Implementation status

## 0.4.0

| Area | Implemented |
| --- | --- |
| CAPTCHA | Manual, automatic on-submit, and adaptive modes. Adaptive falls back to a visible control when the site's pending challenge count reaches the threshold. Existing verification scopes, expiry, origin checks, and one-time consumption apply to every mode. |
| Risk policies | Generic, account, API, fraud, and automation assessments with versioned tenant policies, observe/enforce decisions, feedback, 30-day retention, and a scoped event list. Customers can load a prior revision and publish its settings as a new version. |
| Automation and alerts | Registered site automation keys and separately recorded self-declarations. The Protection Events page shows recent tenant-local velocity and customer-reported abuse alerts. |
| Billing | Published plan catalog, gateway-specific prices, tenant checkout choice, server-signed checkout quotes, immutable invoice and allowance terms, and invoice CSV. The administrator still chooses which payment providers and single SMS provider are active. |
| Organization | Verified-email, one-use invitation codes, role management, password-confirmed ownership transfer, and an owner-only bounded team and audit JSON export. |
| Operations and integration | Scheduler heartbeat and operator status, a CLI mail delivery check, Node.js verification SDK, refreshed OpenAPI and Postman builds, and a new shared-host distribution. |

### Verification

- **77 tests and 1,134 assertions** passed on MariaDB 13.0.2 with PHP 8.5.8, using 101 MB peak PHP test memory. The suite includes InnoDB concurrency, tenant and scope isolation, the billing catalog, mocked provider responses, policy decisions and alerts, team management, and the mail command with an in-memory transport. Pint passed.
- The shared-host ZIP upgraded a private database initialized by 0.3.0. The organization, site and credential, Stripe invoice, Twilio receipt, and provider selection were preserved. Production-configuration preflight, config and view cache, the scheduled heartbeat, and the new cleanup commands passed with fixture settings.
- A single headless Chromium session verified an automatic cross-origin submission through the packaged Laravel application, MariaDB and the PHP consumer. Missing and replayed tokens were rejected. A separate browser fixture in the same session covered manual fallback, keyboard operation, Persian directionality, token expiration and strict CSP.
- Eight Node.js verifier tests passed. Offline npm and Composer installs exercised the browser, Node.js and PHP packages. OpenAPI references, Postman request paths and collection variables were checked against the generated contract.

### Remaining production gates

- **Live infrastructure** requires the intended host to pass HTTPS and proxy validation, real mail delivery, scheduled cleanup, backup and restore, and capacity measurements. Independently review the challenge protocol and accessibility before commercial rollout.
- **Providers** require approved merchant accounts and messaging credentials to run the end-to-end checkout and OTP tests on the intended host. Recurring collections, in-app refunds, and SMS carrier callbacks remain open.
- **Product backlog** includes alternative CAPTCHA providers, broader browser and CMS workflows, advanced fraud review and crawler controls, off-platform alerts, additional SDKs and localization, enterprise entitlements, SSO, and edge or HA profiles. [Requirements traceability](REQUIREMENTS_TRACEABILITY.md)

## 0.3.0 provider selection

- **Provider settings** at `/admin/provider-settings` lets MFA-protected operators enable zero, one or both payment gateways and select Kavenegar, Twilio or Disabled for SMS. Saving requires current-password confirmation and is audited. Legacy environment flags provide defaults until the first explicit save.
- Customers can choose enabled, configured Stripe or ZarinPal fixed-term checkout. Stripe uses signed webhooks and checks the provider session against its invoice before fulfillment. Deselecting a gateway stops new checkout and hides the existing hosted link while preserving payment verification.
- Direct OTP delivery follows the one selected provider. Site credentials are kept separately and encrypted. Existing delivery receipts retain their provider, and replay reuses their result across switches. See [provider setup](docs/providers.md) for live account configuration and destination coverage.
- Verified 56 tests and 708 assertions on MariaDB 13.0.2, with 97 MB peak PHP test memory and mocked provider responses. The 0.2.0 schema upgraded in place with the legacy ZarinPal invoice amount and Kavenegar receipt preserved. No browser windows, live calls, or charges were needed.

## 0.2.0 billing and SMS

- ZarinPal hosted checkout supports one configurable IRR offer with fixed terms and manual renewals. Customers get tenant-scoped invoice receipts. Verified live payments grant the purchased production quota exactly once, and sandbox verification stays isolated. Operators can inspect invoices and recheck a pending payment using its stored gateway context.
- Kavenegar sends approved OTP templates for provisioned production sites. Each site has an encrypted provider key and a distinct Shield send scope. The dispatch claim and destination controls run before the provider call. Duplicate or ambiguous requests preserve the existing dispatch state. Delivery history reports provider acceptance separately from a final carrier delivery result.
- [Provider setup](docs/providers.md) covers the required deployment configuration. Both integrations start disabled. Provider tests use mocked responses. Live activation requires a deployed merchant account, an approved SMS template and provider credentials, and an authorized test on the intended host.
- MariaDB 13.0.2 ran 42 tests and 521 assertions with 95 MB peak PHP test memory. This includes the core InnoDB race test and fixture coverage for payment replay, billing isolation, allowance changes, SMS timeouts, oversized responses and duplicate sends. The prior release's MariaDB schema upgraded successfully with its fixture tenant preserved, and provider preflight passed against synthetic configuration.
- The patched application uses Laravel 13.44.1, Filament 5.12.0 and Livewire 4.4.10. The fresh advisory feed returned data for all 139 locked packages and Composer's version matcher found zero applicable advisories. [Packagist advisory API](https://packagist.org/apidoc#list-security-advisories)

Automated recurring payments, refunds inside Shield, multiple plan tiers, and carrier delivery callbacks were outside the 0.2.0 checkpoint.

## 0.1.0 core checkpoint

The working application includes customer and operator Filament panels, tenant-scoped sites and credentials, a standalone checkbox proof-of-work widget, one-time backend verification, fixed-window abuse controls, SMS pre-send decisions, customer activity counts, a public English/Persian quick start and package downloads. The PHP consumer runs on a separate origin and gates its form submission on the verification API. The site playground previews the browser step using a configured sandbox key.

### 0.1.0 local evidence

- Composer resolved the PHP 8.3 platform to Laravel 13.31.0, Filament 5.7.6 and Livewire 4.4.2. Actual CLI checks used PHP 8.5.8. Live Packagist metadata requests timed out while downloading the locked upstream packages succeeded. No fresh online advisory audit was completed.
- `DB_CONNECTION=mysql DB_DATABASE=shield_test ... php vendor/bin/phpunit --no-progress` passed **19 tests and 284 assertions** against MariaDB 13.0.2 with 83 MB peak PHP memory. The two-worker race test allowed exactly one successful solve and one token consume. The suite also covered tenant isolation, audit, scoped keys, domain proof fixtures, CORS, quotas, SMS and public routes.
- One headless Chromium session verified the real cross-origin flow on Laravel and MariaDB through the PHP SDK. Missing and replayed tokens returned HTTP 422. An owned browser fixture exercised strict CSP, keyboard interaction, two independent instances, Persian directionality and expiry.
- Offline npm and Composer installed the archives. WordPress 7.1.4 and WooCommerce 11.2.1 activated the actual plugin in a separate local database. Mocked verification exercised native login, registration, reset request, comments and classic WooCommerce login and registration. Browser CMS workflows, checkout, Blocks and Store API were outside this test.
- Unpacking the shared-host ZIP yielded a working PHP application with production dependencies and published assets. Fresh MariaDB migrations, production-config preflight, Blade/config cache commands, a dump and restore of local fixture records, and the two bounded cleanup commands passed. Production mail and the actual control-panel server were outside this smoke test.
- Release artifacts, paths and digest checks are recorded in [the generated manifest](downloads/manifest.json). Catalog status remains conservative where platform testing covers only a subset. Use `node scripts/build-releases.mjs --check` to verify checksums.
- The core workflow configures PHP 8.3, MariaDB 11.4, locked dependency checks, PHPUnit and artifact integrity. It has not run remotely.

No live shared-control-panel host, production mail or payment account was used. Capacity tests and multi-browser matrices were deferred on the active workstation. [Requirements traceability](REQUIREMENTS_TRACEABILITY.md) records the full backlog with evidence and priorities.
