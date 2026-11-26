import assert from 'node:assert/strict';
import { createHash, randomBytes, randomUUID } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { createServer } from 'node:http';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require(process.env.SHIELD_PLAYWRIGHT_MODULE || 'playwright');
const challengeId = randomUUID();
const siteKey = 'site_test_' + '1'.repeat(32);
const verifySecret = 'sk_verify_' + '2'.repeat(64);
const salt = '3'.repeat(32);
const solution = 684;
const expectedAction = 'contact';
const challenge = createHash('sha256').update(salt + solution).digest('hex');
const issued = new Map();
let challengeExpiry = 12000;
let settingsMode = 'invisible';
let customerOrigin;
let providerOrigin;

function reply(response, status, payload, headers = {}) {
    response.writeHead(status, { 'Content-Type': 'application/json', ...headers });
    response.end(JSON.stringify(payload));
}

function expiry(delay) {
    return new Date(Date.now() + delay).toISOString();
}

function listen(server) {
    return new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(0, '127.0.0.1', () => resolve('http://127.0.0.1:' + server.address().port));
    });
}

async function body(request) {
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    return JSON.parse(Buffer.concat(chunks).toString());
}

const api = createServer(async (request, response) => {
    const path = request.url;
    if (path === '/assets/captcha/v1/widget.js' || path === '/assets/captcha/v1/widget.css') {
        const content = await readFile(new URL('../public' + path, import.meta.url));
        response.writeHead(200, { 'Content-Type': path.endsWith('js') ? 'application/javascript' : 'text/css' });
        response.end(content);
        return;
    }
    if (path === '/api/v1/verify') {
        const payload = await body(request);
        const expires = issued.get(payload.token);
        if (
            request.headers.authorization === 'Bearer ' + verifySecret
            && expires > Date.now() && payload.site_key === siteKey && payload.expected_action === expectedAction
            && payload.expected_origin === customerOrigin && payload.expected_environment === 'sandbox'
        ) {
            issued.delete(payload.token);
            reply(response, 200, {
                success: true, decision: 'allow', site_key: siteKey, action: expectedAction,
                origin: customerOrigin, environment: 'sandbox',
            });
        } else {
            reply(response, 422, { success: false });
        }
        return;
    }
    const cors = {
        'Access-Control-Allow-Origin': customerOrigin,
        'Access-Control-Allow-Headers': 'content-type, accept',
        'Access-Control-Allow-Methods': 'POST',
        'Vary': 'Origin',
    };
    if (request.headers.origin !== customerOrigin) {
        reply(response, 403, { success: false });
    } else if (request.method === 'OPTIONS') {
        response.writeHead(204, cors);
        response.end();
    } else {
        const payload = await body(request);
        if (payload.site_key !== siteKey || payload.action !== expectedAction) {
            reply(response, 403, { success: false }, cors);
        } else if (path === '/api/v1/widget/config') {
            reply(response, 200, { site_key: siteKey, action: expectedAction, mode: settingsMode }, cors);
        } else if (path === '/api/v1/challenges') {
            reply(response, 200, {
                id: challengeId, salt, challenge, max_number: 2000, expires_at: expiry(15000),
            }, cors);
        } else if (path === '/api/v1/challenges/' + challengeId + '/solve' && payload.number === solution) {
            const token = randomBytes(32).toString('hex');
            issued.set(token, Date.now() + challengeExpiry);
            reply(response, 200, { token, expires_at: expiry(challengeExpiry) }, cors);
        } else {
            reply(response, 422, { success: false }, cors);
        }
    }
});

const customer = createServer(async (request, response) => {
    if (request.method === 'POST') {
        let input = '';
        for await (const chunk of request) input += chunk;
        const form = new URLSearchParams(input);
        const verification = await fetch(providerOrigin + '/api/v1/verify', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + verifySecret, 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: form.get('shield-captcha-response'), site_key: siteKey, expected_action: expectedAction,
                expected_origin: customerOrigin, expected_environment: 'sandbox',
            }),
        });
        response.writeHead(verification.status, { 'Content-Type': 'text/plain' });
        response.end(verification.ok ? 'protected operation accepted' : 'verification rejected');
        return;
    }

    response.writeHead(200, {
        'Content-Type': 'text/html; charset=utf-8',
        'Content-Security-Policy': "default-src 'none'; script-src " + providerOrigin
            + '; style-src ' + providerOrigin + '; connect-src ' + providerOrigin + "; worker-src blob:; form-action 'self'",
    });
    response.end(`<!doctype html><html lang="en"><meta charset="utf-8"><title>Widget test</title>
        <script defer src="${providerOrigin}/assets/captcha/v1/widget.js"></script>
        <form id="first" method="POST">
          <div data-shield-captcha data-site-key="${siteKey}" data-action="contact"${request.url === '/managed' ? ' data-mode="managed"' : ''}>Enable JavaScript.</div>
          <button type="submit" name="intent" value="send">Submit</button>
        </form>
        <form id="second" method="POST">
          <div id="manual"></div>
          <button type="submit">ارسال</button>
        </form>
    </html>`);
});

let browser;
try {
    providerOrigin = await listen(api);
    customerOrigin = await listen(customer);
    browser = await chromium.launch({
        headless: true,
        executablePath: process.env.SHIELD_CHROME_EXECUTABLE,
        args: ['--disable-gpu', '--renderer-process-limit=2'],
        timeout: 20000,
    });
    const page = await browser.newPage({ viewport: { width: 375, height: 667 } });
    page.setDefaultTimeout(15000);
    try {
        await page.goto(customerOrigin);
        const button = page.getByRole('button', { name: 'Verify this request' });
        await button.waitFor();
        await page.locator('#first button[type="submit"]').click();
        await page.getByText('Complete verification before submitting.').waitFor();
        await page.keyboard.press('Enter');
        await page.getByText('Verified. You can submit the form.').waitFor();
        const token = await page.locator('#first input[name="shield-captcha-response"]').inputValue();
        assert.match(token, /^[a-f0-9]{64}$/);

        const second = await page.evaluate((key) => {
            const container = document.getElementById('manual');
            const options = { siteKey: key, action: 'contact', locale: 'fa', theme: 'dark' };
            const id = window.ShieldCaptcha.render(container, options);
            return [id, window.ShieldCaptcha.render(container, options)];
        }, siteKey);
        assert.equal(second[0], second[1]);

        challengeExpiry = 1500;
        await page.getByRole('button', { name: 'تأیید درخواست' }).click();
        await page.getByText('درخواست تأیید شد. می‌توانید فرم را ارسال کنید.').waitFor();
        const secondToken = await page.evaluate((id) => window.ShieldCaptcha.getResponse(id), second[0]);
        assert.notEqual(secondToken, token);
        assert.equal(await page.locator('#manual .widget').getAttribute('dir'), 'rtl');
        await page.getByText('مهلت تأیید پایان یافت. دوباره تلاش کنید.').waitFor();
        assert.equal(await page.evaluate((id) => window.ShieldCaptcha.getResponse(id), second[0]), '');
        await page.evaluate((id) => window.ShieldCaptcha.remove(id), second[0]);
        assert.equal(await page.locator('#manual input').count(), 0);

        await Promise.all([
            page.waitForNavigation(),
            page.locator('#first button[type="submit"]').click(),
        ]);
        await page.getByText('protected operation accepted').waitFor();
        const replay = await fetch(customerOrigin, {
            method: 'POST',
            body: new URLSearchParams({ 'shield-captcha-response': token }),
        });
        assert.equal(replay.status, 422);

        challengeExpiry = 12000;
        await page.goto(customerOrigin + '/managed');
        const submission = page.waitForRequest((request) => request.method() === 'POST' && request.url() === customerOrigin + '/managed');
        await page.locator('#first button[type="submit"]').click();
        assert.equal(new URLSearchParams((await submission).postData()).get('intent'), 'send');
        await page.getByText('protected operation accepted').waitFor();

        settingsMode = 'checkbox';
        await page.goto(customerOrigin + '/managed');
        await page.locator('#first button[type="submit"]').click();
        await page.getByText('Complete verification before submitting.').waitFor();
        assert.equal(await page.locator('#first input[name="shield-captcha-response"]').inputValue(), '');
        await page.keyboard.press('Enter');
        await page.getByText('Verified. You can submit the form.').waitFor();

        if (process.env.SHIELD_REAL_CUSTOMER_ORIGIN) {
            const liveCustomer = new URL(process.env.SHIELD_REAL_CUSTOMER_ORIGIN);
            assert.equal(liveCustomer.hostname, '127.0.0.1');
            const fresh = await fetch(liveCustomer, {
                method: 'POST',
                body: new URLSearchParams({ 'shield-captcha-response': '' }),
            });
            assert.equal(fresh.status, 422);
            await page.goto(liveCustomer.href);
            if (process.env.SHIELD_REAL_MANAGED !== '1') {
                await page.getByRole('button', { name: 'Verify this request' }).click();
                await page.getByText('Verified. You can submit the form.').waitFor();
            }
            const protectedSubmission = page.waitForRequest((request) => request.method() === 'POST' && request.url() === liveCustomer.href);
            await page.getByRole('button', { name: 'Submit' }).click();
            const freshToken = new URLSearchParams((await protectedSubmission).postData()).get('shield-captcha-response');
            assert.match(freshToken, /^[a-f0-9]{64}$/);
            await page.getByText('Accepted sandbox submission.').waitFor();
            const reused = await fetch(liveCustomer, {
                method: 'POST',
                body: new URLSearchParams({ 'shield-captcha-response': freshToken }),
            });
            assert.equal(reused.status, 422);
            console.log('Headless Chromium verified the Laravel and MariaDB cross-origin PHP SDK workflow, plus missing-token and replay rejection.');
        }
    } catch (error) {
        process.stderr.write((await page.locator('body').innerText({ timeout: 1000 }).catch(() => 'Page unavailable')) + '\n');
        throw error;
    }
    console.log('Headless Chromium passed the two-origin CSP, keyboard, token expiry, multi-instance, managed submit and manual fallback smoke.');
} finally {
    await browser?.close();
    api.close();
    customer.close();
}
