# Shield CAPTCHA release artifacts

The manifest lists development artifacts built from this repository. See the implementation status for the evidence and integration gaps in each release.

```bash
node scripts/build-releases.mjs
node scripts/build-releases.mjs --check
```

The build requires Node.js, npm and zip, uses an explicit source file list and runs without registry access. Production PHP serves the generated files. This build does not publish to registries, does not sign releases, and does not assert byte-for-byte reproducibility.

## Development verification

- `node scripts/test-package-installs.mjs` installed the JavaScript and PHP archives offline with npm and Composer, compared the installed browser assets with the hosted files, and exercised PHP response-context checks.
- `node scripts/test-widget.mjs` passed in a single headless Chrome 158 session with Playwright 1.63. The fixture checked two origins, a strict content security policy, keyboard use, independent widget instances, Persian RTL, expiration, and server-side consumption. With a live local consumer origin configured, it also exercised the actual Laravel API, MariaDB and PHP verifier and confirmed HTTP 422 for missing and replayed tokens.
- `sh scripts/test-wordpress.sh` activated the built ZIP with WordPress 7.1.4 and WooCommerce 11.2.1 on a disposable MariaDB instance. Tests covered the core login, registration, password reset request and comment hooks plus classic WooCommerce login and account registration with a mocked verification service. Live browser CMS forms, WooCommerce checkout and Store API were outside this run.

The archive manifest records build status. The runs above cover local fixtures and do not establish support on every advertised platform or hosting provider.
