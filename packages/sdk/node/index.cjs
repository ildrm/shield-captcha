'use strict';

const { isIP } = require('node:net');
const { performance } = require('node:perf_hooks');
const { URL } = require('node:url');

const MAX_RESPONSE_BYTES = 16_384;
const TIMEOUT_MS = 3_000;
const HOSTNAME = /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/;
const LOOPBACK = new Set(['localhost', '127.0.0.1', '[::1]']);

function parseOrigin(value) {
    if (typeof value !== 'string' || value.length > 255
        || !/^https?:\/\/[a-z0-9.\-:[\]]+$/.test(value) || !URL.canParse(value)) {
        return null;
    }
    const url = new URL(value);
    const ip = isIP(url.hostname.replace(/^\[|\]$/g, ''));
    if (url.origin !== value || url.port === '0' || (!ip && !HOSTNAME.test(url.hostname))) {
        return null;
    }
    return url;
}

function isProductionOrigin(url) {
    return url.protocol === 'https:' && url.hostname.includes('.') && !isIP(url.hostname);
}

async function readJson(body) {
    if (body === null) {
        return null;
    }
    const buffer = new Uint8Array(MAX_RESPONSE_BYTES);
    let length = 0;
    for await (const chunk of body) {
        if (length + chunk.byteLength > MAX_RESPONSE_BYTES) {
            return null;
        }
        buffer.set(chunk, length);
        length += chunk.byteLength;
    }
    return JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(buffer.subarray(0, length)));
}

class Verifier {
    #apiOrigin;
    #productionAllowed;
    #secret;

    constructor(apiOrigin, secret) {
        const url = parseOrigin(apiOrigin);
        if (url === null || (!isProductionOrigin(url) && !LOOPBACK.has(url.hostname))
            || typeof secret !== 'string' || !/^sk_verify_[a-f0-9]{64}$/.test(secret)) {
            throw new TypeError('Invalid Shield CAPTCHA server configuration.');
        }
        this.#apiOrigin = url.origin;
        this.#productionAllowed = isProductionOrigin(url);
        this.#secret = secret;
    }

    async verify(token, siteKey, action, origin, environment = 'production') {
        const url = parseOrigin(origin);
        const sandbox = environment === 'sandbox';
        const siteKeyPattern = sandbox ? /^site_test_[a-f0-9]{32}$/ : /^site_prod_[a-f0-9]{32}$/;
        if ((environment !== 'production' && !sandbox)
            || typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)
            || typeof siteKey !== 'string' || !siteKeyPattern.test(siteKey)
            || typeof action !== 'string' || !/^[a-z][a-z0-9_-]{0,63}$/.test(action)
            || url === null || (!sandbox && (!this.#productionAllowed || !isProductionOrigin(url)))) {
            return false;
        }

        const controller = new AbortController();
        const deadline = performance.now() + TIMEOUT_MS;
        const timeout = setTimeout(() => controller.abort(), TIMEOUT_MS);
        try {
            const response = await fetch(this.#apiOrigin + '/api/v1/verify', {
                method: 'POST',
                redirect: 'error',
                signal: controller.signal,
                headers: {
                    'Authorization': 'Bearer ' + this.#secret,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    token,
                    site_key: siteKey,
                    expected_action: action,
                    expected_origin: origin,
                    expected_environment: environment,
                }),
            });
            const contentType = response.headers.get('content-type')?.split(';', 1)[0].trim().toLowerCase();
            if (response.status !== 200 || response.redirected || contentType !== 'application/json'
                || Number(response.headers.get('content-length')) > MAX_RESPONSE_BYTES) {
                return false;
            }

            const result = await readJson(response.body);
            return !controller.signal.aborted && performance.now() < deadline
                && result?.success === true
                && result.decision === 'allow'
                && result.site_key === siteKey
                && result.action === action
                && result.origin === origin
                && result.environment === environment;
        } catch {
            return false;
        } finally {
            clearTimeout(timeout);
            controller.abort();
        }
    }
}

exports.Verifier = Verifier;
