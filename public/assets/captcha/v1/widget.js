(function () {
    'use strict';

    if (window.ShieldCaptcha) return;

    const path = '/assets/captcha/v1/';
    const script = document.currentScript?.src || new URL(path + 'widget.js', location.href);
    const stylesheet = new URL('widget.css', script).href;
    const defaultApi = new URL(script).origin;
    const instances = new Map();
    const mounted = new WeakMap();
    const messages = {
        en: {
            start: 'Verify this request',
            working: 'Verifying…',
            checking: 'Checking verification settings…',
            ready: 'Verified. You can submit the form.',
            expired: 'Verification expired. Please try again.',
            retry: 'Verification failed. Please try again.',
            timeout: 'Verification timed out. Please try again.',
            unavailable: 'Verification requires JavaScript and a supported browser on a secure page.',
            required: 'Complete verification before submitting.',
            limited: 'Too many attempts. Wait a moment before trying again.',
        },
        fa: {
            start: 'تأیید درخواست',
            working: 'در حال بررسی…',
            checking: 'در حال بررسی تنظیمات تأیید…',
            ready: 'درخواست تأیید شد. می‌توانید فرم را ارسال کنید.',
            expired: 'مهلت تأیید پایان یافت. دوباره تلاش کنید.',
            retry: 'تأیید انجام نشد. دوباره تلاش کنید.',
            timeout: 'زمان بررسی پایان یافت. دوباره تلاش کنید.',
            unavailable: 'تأیید به جاوااسکریپت، مرورگر پشتیبانی‌شده و صفحهٔ امن نیاز دارد.',
            required: 'پیش از ارسال، تأیید را کامل کنید.',
            limited: 'تعداد تلاش‌ها زیاد است. کمی صبر کنید و دوباره تلاش کنید.',
        },
    };
    let sequence = 0;

    // Included in the trusted widget asset so embedding sites need no cross-origin worker script.
    function workerMain() {
        self.onmessage = async ({ data: { salt, challenge, max_number: max } }) => {
            try {
                const encoder = new TextEncoder();
                const expected = new Uint8Array(challenge.match(/../g).map((pair) => parseInt(pair, 16)));
                for (let number = 0; number <= max; number++) {
                    const bytes = encoder.encode(salt + number);
                    const digest = new Uint8Array(await crypto.subtle.digest('SHA-256', bytes));
                    if (digest.every((byte, index) => byte === expected[index])) {
                        self.postMessage({ number });
                        return;
                    }
                }
            } catch {
                // Errors are represented by the same retryable UI state as an unsolved challenge.
            }
            self.postMessage({ number: -1 });
        };
    }

    function solve(challenge, signal) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(new Blob(['(' + workerMain.toString() + ')()'], {
                type: 'application/javascript',
            }));
            let worker;
            try {
                worker = new Worker(url);
            } catch (error) {
                URL.revokeObjectURL(url);
                reject(error);
                return;
            }

            function finish() {
                signal.removeEventListener('abort', abort);
                worker.terminate();
                URL.revokeObjectURL(url);
            }
            function abort() {
                finish();
                reject(signal.reason);
            }

            signal.addEventListener('abort', abort, { once: true });
            worker.onerror = () => {
                finish();
                reject(new Error('worker_failed'));
            };
            worker.onmessage = ({ data }) => {
                finish();
                if (Number.isInteger(data.number) && data.number >= 0 && data.number <= challenge.max_number) {
                    resolve(data.number);
                } else {
                    reject(new Error('solution_unavailable'));
                }
            };
            worker.postMessage({
                salt: challenge.salt,
                challenge: challenge.challenge,
                max_number: challenge.max_number,
            });
        });
    }

    async function request(instance, endpoint, payload, signal) {
        const response = await fetch(instance.api + '/api/v1/' + endpoint, {
            method: 'POST',
            credentials: 'omit',
            cache: 'no-store',
            redirect: 'error',
            referrerPolicy: 'no-referrer',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            signal,
        });
        const data = await response.json();
        if (!response.ok || data?.success === false) {
            throw new Error(response.status === 429 ? 'rate_limited' : 'request_failed');
        }
        return data;
    }

    function show(instance, state, text) {
        instance.ui.dataset.state = state;
        instance.button.setAttribute('aria-disabled', String(state === 'working' || state === 'ready'));
        instance.status.textContent = instance.copy[text];
    }

    function notify(instance, event, detail) {
        instance.container.dispatchEvent(new CustomEvent('shieldcaptcha:' + event, { detail }));
        const callback = instance.callbacks[event];
        if (typeof callback === 'function') queueMicrotask(() => callback(detail));
    }

    function clear(instance) {
        clearTimeout(instance.expiryTimer);
        instance.expires = 0;
        instance.input.value = '';
    }

    function currentResponse(instance) {
        if (instance.expires && instance.expires <= Date.now()) {
            clear(instance);
            show(instance, 'idle', 'expired');
            notify(instance, 'expire');
        }
        return instance.input.value;
    }

    function execute(id) {
        const instance = instances.get(id);
        if (!instance) return Promise.resolve(null);
        if (instance.pending) return instance.pending;
        const existing = currentResponse(instance);
        if (existing) return Promise.resolve(existing);
        if (!window.isSecureContext || !window.Worker || !window.crypto?.subtle) {
            show(instance, 'error', 'unavailable');
            notify(instance, 'error', { code: 'unsupported_browser' });
            return Promise.resolve(null);
        }

        const controller = new AbortController();
        instance.controller = controller;
        show(instance, 'working', 'working');
        const timer = setTimeout(() => controller.abort(new Error('timeout')), 20000);
        const active = () => instance.controller === controller;

        instance.pending = (async () => {
            try {
                const context = { site_key: instance.siteKey, action: instance.action };
                const challenge = await request(instance, 'challenges', context, controller.signal);
                if (!active()) return null;
                if (
                    typeof challenge?.id !== 'string' || !/^[A-Za-z0-9_-]{1,100}$/.test(challenge.id)
                    || typeof challenge.salt !== 'string' || !/^[a-f0-9]{16,128}$/.test(challenge.salt)
                    || typeof challenge.challenge !== 'string' || !/^[a-f0-9]{64}$/.test(challenge.challenge)
                    || !Number.isInteger(challenge.max_number) || challenge.max_number < 0 || challenge.max_number > 50000
                ) {
                    throw new Error('invalid_challenge');
                }
                if (controller.signal.aborted) throw controller.signal.reason;
                const number = await solve(challenge, controller.signal);
                if (!active()) return null;
                const result = await request(instance, 'challenges/' + challenge.id + '/solve', {
                    ...context,
                    number,
                }, controller.signal);
                if (!active()) return null;
                const expires = Date.parse(result?.expires_at);
                if (
                    typeof result?.token !== 'string' || !/^[A-Za-z0-9_-]{1,256}$/.test(result.token)
                    || !Number.isFinite(expires) || expires <= Date.now() || expires > Date.now() + 300000
                ) {
                    throw new Error('invalid_token');
                }
                instance.expires = expires;
                instance.expiryTimer = setTimeout(() => currentResponse(instance), expires - Date.now());
                instance.input.value = result.token;
                show(instance, 'ready', 'ready');
                notify(instance, 'success', { token: result.token });
                return result.token;
            } catch (error) {
                if (active()) {
                    const timedOut = controller.signal.reason?.message === 'timeout';
                    const limited = error?.message === 'rate_limited';
                    show(instance, 'error', timedOut ? 'timeout' : limited ? 'limited' : 'retry');
                    notify(instance, 'error', { code: timedOut ? 'timeout' : limited ? 'rate_limited' : 'verification_failed' });
                }
                return null;
            } finally {
                clearTimeout(timer);
                if (active()) {
                    instance.pending = null;
                    instance.controller = null;
                }
            }
        })();

        return instance.pending;
    }

    function reset(id) {
        const instance = instances.get(id);
        if (!instance) return;
        instance.controller?.abort();
        instance.submitController?.abort();
        instance.submitController = null;
        instance.controller = null;
        instance.pending = null;
        clear(instance);
        show(instance, 'idle', 'start');
    }

    async function verifySubmission(instance, id, submitter) {
        const controller = new AbortController();
        instance.submitController = controller;
        show(instance, 'working', 'checking');
        const timer = setTimeout(() => controller.abort(), 4000);
        const active = () => instance.submitController === controller;

        try {
            const settings = await request(instance, 'widget/config', {
                site_key: instance.siteKey,
                action: instance.action,
            }, controller.signal);
            clearTimeout(timer);
            if (!active() || controller.signal.aborted || settings?.mode !== 'invisible'
                || settings.site_key !== instance.siteKey || settings.action !== instance.action
                || document.visibilityState !== 'visible') return;

            const token = await execute(id);
            if (token && active() && document.visibilityState === 'visible' && instance.form.isConnected
                && (!submitter || submitter.form === instance.form)) {
                instance.submitController = null;
                instance.form.requestSubmit(submitter || undefined);
            }
        } catch {
            // A config failure leaves the manual action available on the current form.
        } finally {
            clearTimeout(timer);
            if (active()) {
                instance.submitController = null;
                if (!instance.pending && !currentResponse(instance)) {
                    if (instance.ui.dataset.state === 'working') show(instance, 'idle', 'required');
                    instance.button.focus();
                }
            }
        }
    }

    function remove(id) {
        const instance = instances.get(id);
        if (!instance) return;
        reset(id);
        instance.form?.removeEventListener('submit', instance.onSubmit, true);
        instance.container.replaceChildren();
        mounted.delete(instance.container);
        instances.delete(id);
    }

    function render(container, options) {
        if (!(container instanceof HTMLElement)) throw new TypeError('Provide a widget container element.');
        if (mounted.has(container)) return mounted.get(container);
        if (
            typeof options?.siteKey !== 'string' || !/^[A-Za-z0-9_-]{1,100}$/.test(options.siteKey)
            || typeof options.action !== 'string' || !/^[a-z][a-z0-9_.:-]{0,63}$/.test(options.action)
        ) {
            throw new TypeError('Provide a valid site key and registered action.');
        }
        const api = new URL(options.apiOrigin || defaultApi);
        const local = ['localhost', '127.0.0.1', '[::1]'].includes(api.hostname);
        if (api.origin + '/' !== api.href || !(api.protocol === 'https:' || (local && api.protocol === 'http:'))) {
            throw new TypeError('Provide an HTTPS API origin or loopback sandbox origin.');
        }
        const locale = options.locale === 'fa' ? 'fa' : 'en';
        const id = 'shield-captcha-' + ++sequence;
        const host = document.createElement('div');
        const root = host.attachShadow({ mode: 'open' });
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = stylesheet;
        const ui = document.createElement('div');
        ui.className = 'widget';
        ui.dataset.theme = ['light', 'dark'].includes(options.theme) ? options.theme : 'auto';
        ui.lang = locale;
        ui.dir = locale === 'fa' ? 'rtl' : 'ltr';
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = messages[locale].start;
        const status = document.createElement('p');
        status.id = id + '-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-atomic', 'true');
        button.setAttribute('aria-describedby', status.id);
        ui.append(button, status);
        root.append(css, ui);
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = options.responseName || 'shield-captcha-response';
        const form = container.closest('form');
        const onSubmit = (event) => {
            if (!currentResponse(instance)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                if (instance.submitController) return;
                if (!instance.pending && instance.managed) {
                    void verifySubmission(instance, id, event.submitter);
                    return;
                }
                if (!instance.pending) show(instance, 'idle', 'required');
                button.focus();
            }
        };
        const instance = {
            container, api: api.origin, ui, status, button, form, onSubmit, input,
            siteKey: options.siteKey, action: options.action, copy: messages[locale],
            managed: options.mode === 'managed',
            callbacks: { success: options.onSuccess, error: options.onError, expire: options.onExpire },
        };
        button.addEventListener('click', () => execute(id));
        if (options.guardSubmit !== false) form?.addEventListener('submit', onSubmit, true);
        container.replaceChildren(host, input);
        mounted.set(container, id);
        instances.set(id, instance);
        return id;
    }

    function mountAll(root = document) {
        for (const element of root.querySelectorAll('[data-shield-captcha]')) {
            if (mounted.has(element)) continue;
            try {
                render(element, {
                    siteKey: element.dataset.siteKey,
                    action: element.dataset.action,
                    locale: element.dataset.locale,
                    theme: element.dataset.theme,
                    mode: element.dataset.mode,
                    apiOrigin: element.dataset.apiOrigin,
                    responseName: element.dataset.responseName,
                });
            } catch {
                element.textContent = messages[element.dataset.locale === 'fa' ? 'fa' : 'en'].unavailable;
            }
        }
    }

    window.ShieldCaptcha = Object.freeze({
        render, execute, reset, remove, mountAll,
        getResponse(id) {
            const instance = instances.get(id);
            return instance ? currentResponse(instance) : '';
        },
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => mountAll(), { once: true });
    } else {
        mountAll();
    }
})();
