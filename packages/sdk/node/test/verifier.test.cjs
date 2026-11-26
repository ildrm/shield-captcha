'use strict';

const assert = require('node:assert/strict');
const { test } = require('node:test');
const { Verifier } = require('@shield-captcha/verify');

const API_ORIGIN = 'https://shield.example.com';
const SITE_ORIGIN = 'https://shop.example.com';
const SECRET = 'sk_verify_' + 'a'.repeat(64);
const TOKEN = 'b'.repeat(64);
const SITE_KEY = 'site_prod_' + 'c'.repeat(32);
const TEST_KEY = 'site_test_' + 'c'.repeat(32);
const ACTION = 'contact_form';

function allowed(overrides = {}) {
    return {
        success: true,
        decision: 'allow',
        site_key: SITE_KEY,
        action: ACTION,
        origin: SITE_ORIGIN,
        environment: 'production',
        ...overrides,
    };
}

function verify() {
    return new Verifier(API_ORIGIN, SECRET).verify(TOKEN, SITE_KEY, ACTION, SITE_ORIGIN);
}

test('ESM and CommonJS expose the same verifier', async () => {
    assert.equal((await import('@shield-captcha/verify')).Verifier, Verifier);
});

test('posts the token with fixed context and accepts an exact allow response', async (t) => {
    const request = t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(url, API_ORIGIN + '/api/v1/verify');
        assert.equal(options.method, 'POST');
        assert.equal(options.redirect, 'error');
        assert.equal(options.headers.Authorization, 'Bearer ' + SECRET);
        assert.deepEqual(JSON.parse(options.body), {
            token: TOKEN,
            site_key: SITE_KEY,
            expected_action: ACTION,
            expected_origin: SITE_ORIGIN,
            expected_environment: 'production',
        });
        return Response.json(allowed({ verified_at: '2026-11-26T10:11:12Z' }));
    });
    assert.equal(await verify(), true);
    assert.equal(request.mock.callCount(), 1);
});

test('rejects invalid configuration and verification inputs before calling fetch', async (t) => {
    const request = t.mock.method(globalThis, 'fetch', async () => Response.json(allowed()));
    for (const origin of [
        'http://shield.example.com', API_ORIGIN + '/', API_ORIGIN + ':443', API_ORIGIN + '?debug=true',
        'https://name:pw@shield.example.com', 'https://shield.example.com:0', 'https://-bad.example.com',
    ]) {
        assert.throws(() => new Verifier(origin, SECRET), TypeError);
    }
    assert.throws(() => new Verifier(API_ORIGIN, SECRET + '\n'), TypeError);
    const verifier = new Verifier(API_ORIGIN, SECRET);
    assert.equal(await verifier.verify(null, SITE_KEY, ACTION, SITE_ORIGIN), false);
    assert.equal(await verifier.verify(TOKEN + '\n', SITE_KEY, ACTION, SITE_ORIGIN), false);
    assert.equal(await verifier.verify(TOKEN, SITE_KEY, 'contact.form', SITE_ORIGIN), false);
    assert.equal(await verifier.verify(TOKEN, TEST_KEY, ACTION, SITE_ORIGIN), false);
    assert.equal(await verifier.verify(TOKEN, SITE_KEY, ACTION, 'http://localhost:8000'), false);
    assert.equal(await verifier.verify(TOKEN, SITE_KEY, ACTION, SITE_ORIGIN, 'invalid'), false);
    assert.equal(request.mock.callCount(), 0);
});

test('allows a loopback API and fixture origin for sandbox only', async (t) => {
    const apiOrigin = 'http://[::1]:8000';
    const fixtureOrigin = 'http://localhost:8080';
    const verifier = new Verifier(apiOrigin, SECRET);
    const request = t.mock.method(globalThis, 'fetch', async (url, options) => {
        assert.equal(url, apiOrigin + '/api/v1/verify');
        assert.equal(JSON.parse(options.body).expected_environment, 'sandbox');
        return Response.json(allowed({ site_key: TEST_KEY, origin: fixtureOrigin, environment: 'sandbox' }));
    });
    assert.equal(await verifier.verify(TOKEN, SITE_KEY, ACTION, SITE_ORIGIN), false);
    assert.equal(await verifier.verify(TOKEN, TEST_KEY, ACTION, fixtureOrigin, 'sandbox'), true);
    assert.equal(request.mock.callCount(), 1);
});

test('fails closed on every context mismatch and requires a JSON 200 response', async (t) => {
    let reply;
    const request = t.mock.method(globalThis, 'fetch', async () => reply);
    for (const overrides of [
        { success: 'true' }, { decision: 'deny' }, { site_key: TEST_KEY }, { action: 'checkout' },
        { origin: 'https://other.example.com' }, { environment: 'sandbox' },
    ]) {
        reply = Response.json(allowed(overrides));
        assert.equal(await verify(), false);
    }

    reply = Response.json(allowed(), { status: 422 });
    assert.equal(await verify(), false);
    reply = Response.redirect('https://other.example.com/verify', 302);
    assert.equal(await verify(), false);
    reply = new Response(JSON.stringify(allowed()), { headers: { 'Content-Type': 'text/html' } });
    assert.equal(await verify(), false);
    reply = new Response('{"success":true', { headers: { 'Content-Type': 'application/json' } });
    assert.equal(await verify(), false);
    const badUtf8 = Buffer.from(JSON.stringify(allowed({ details: 'x' })));
    badUtf8[badUtf8.lastIndexOf('x')] = 0xc3;
    reply = new Response(badUtf8, { headers: { 'Content-Type': 'application/json' } });
    assert.equal(await verify(), false);
    assert.equal(request.mock.callCount(), 11);
});

test('rejects an oversized chunked response and cancels its stream', async (t) => {
    const cancel = t.mock.fn();
    const stream = new ReadableStream({
        start(controller) {
            controller.enqueue(new TextEncoder().encode(JSON.stringify(allowed())));
            controller.enqueue(new Uint8Array(16_384));
        },
        cancel,
    });
    t.mock.method(globalThis, 'fetch', async () => new Response(stream, {
        headers: { 'Content-Type': 'application/json' },
    }));

    assert.equal(await verify(), false);
    assert.equal(cancel.mock.callCount(), 1);
});

test('applies its three-second deadline while reading the response body', async (t) => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    const { promise: reading, resolve: beginRead } = Promise.withResolvers();
    const request = t.mock.method(globalThis, 'fetch', async (_url, { signal }) => new Response(new ReadableStream({
        start(controller) {
            controller.enqueue(new TextEncoder().encode(JSON.stringify(allowed())));
            signal.addEventListener('abort', () => controller.error(signal.reason), { once: true });
        },
        pull() {
            beginRead();
        },
    }), { headers: { 'Content-Type': 'application/json' } }));

    const result = verify();
    await reading;
    t.mock.timers.tick(3_000);
    assert.equal(await result, false);
    assert.equal(request.mock.callCount(), 1);
});

test('returns false after a connection failure and sends no retry', async (t) => {
    const request = t.mock.method(globalThis, 'fetch', async () => {
        throw new TypeError('Connection interrupted');
    });
    assert.equal(await verify(), false);
    assert.equal(request.mock.callCount(), 1);
});
