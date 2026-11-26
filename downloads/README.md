# Shield CAPTCHA release artifacts

The manifest lists development artifacts built from this repository. See the implementation status for the evidence and integration gaps in each release.

```bash
node scripts/build-releases.mjs
node scripts/build-releases.mjs --check
```

The build requires Node.js, npm and zip, uses an explicit source file list and runs without registry access. Production PHP serves the generated files. This build does not publish to registries, does not sign releases, and does not assert byte-for-byte reproducibility.

## Development verification

- `node scripts/test-package-installs.mjs` installs the browser, Node.js and PHP archives offline with npm and Composer, compares installed browser assets with hosted files, and exercises the server SDK entry points.
- `node scripts/test-widget.mjs` passed in one headless Chromium session. The fixture checked two origins, a strict content security policy, keyboard use, independent widget instances, Persian RTL, expiration, managed submission, and manual fallback. With a local consumer origin configured, it also exercised the packaged Laravel API, MariaDB and PHP verifier and confirmed HTTP 422 for missing and replayed tokens.
- `sh scripts/test-wordpress.sh` previously activated plugin 0.1.0 with WordPress 7.1.4 and WooCommerce 11.2.1 on a disposable MariaDB instance. Tests covered native login, registration, password reset request and comment hooks plus classic WooCommerce login and account registration with a mocked verification service. The 0.2.0 archive updates the browser widget bundle. Browser CMS forms, WooCommerce checkout and Store API remain open.

The archive manifest records build status. The runs above cover local fixtures and do not establish support on every advertised platform or hosting provider.
