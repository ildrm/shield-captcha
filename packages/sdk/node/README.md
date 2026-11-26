# Shield CAPTCHA verification SDK for Node.js

Use this package to verify a browser-issued Shield CAPTCHA token on your server before performing a protected operation. Version 0.1.0 supports Node.js 22 or later and Shield CAPTCHA API v1. It uses the built-in Fetch API and has no dependencies.

## Install

Download the Node.js verification SDK from your Shield CAPTCHA deployment, then install the archive.

~~~bash
npm install /path/to/downloaded-sdk.tgz
~~~

## Verify a token

Create a verifier with your fixed Shield API origin and server-only verification secret. Keep the expected site key, action, origin, and environment in server configuration. Take only the token from the incoming request.

~~~js
import { Verifier } from '@shield-captcha/verify';

const verifier = new Verifier(
    process.env.SHIELD_API_ORIGIN,
    process.env.SHIELD_VERIFY_SECRET,
);

async function verifyContactForm(formData) {
    return verifier.verify(
        formData.get('shield-captcha-response'),
        process.env.SHIELD_SITE_KEY,
        'contact',
        process.env.SHIELD_SITE_ORIGIN,
        'production',
    );
}
~~~

CommonJS servers can use `const { Verifier } = require('@shield-captcha/verify');`. Both module formats ship with TypeScript declarations.

An allow response must match the expected site key, action, origin, and environment. `verify` returns `false` for invalid input, a denial, a mismatch, or a transport failure. Reject the operation and ask the client for a fresh token after a failed or ambiguous attempt. The SDK makes a single attempt, follows no redirects, caps the response body at 16 KiB, and applies a three-second total deadline.

Use canonical serialized origins such as `https://shop.example.com`, with no slash, default port, path, or credentials. Use a sandbox site key and explicit `sandbox` environment for testing. The API origin may use HTTP on `localhost`, `127.0.0.1`, or `[::1]` in sandbox. Production uses HTTPS with a DNS hostname. Continue enforcing your application's CSRF, authorization, input validation, and idempotency checks.
