# Shield CAPTCHA widget

Use the provider's classic script on a secure consumer page. Register the consumer's exact origin and action in your site settings.

```html
<script src="https://shield.example.test/assets/captcha/v1/widget.js" defer></script>
<form action="/contact" method="post">
    <div data-shield-captcha data-site-key="PUBLIC_SITE_KEY" data-action="contact">
        Enable JavaScript to complete verification.
    </div>
    <button type="submit">Send</button>
</form>
```

The widget writes a short-lived token into `shield-captcha-response`. On the server, verify that token with a server secret and check the expected action, origin, environment and allow decision before authorizing the operation. Never place the verification secret in a page or bundle. A fresh verification is required after the backend consumes a token or a verification attempt returns an ambiguous error.

To vendor the browser assets from the downloaded archive, install with `npm install ./shield-captcha-widget-0.2.0.tgz` and serve `widget.js` and `widget.css` together as static files. Set `data-api-origin` to the exact provider origin if the assets and API have different origins. Keep remote scripts pinned and use the manifest checksum to verify downloaded archives.

Add `data-mode="managed"` or pass `mode: 'managed'` to `render()` to use the site's configured challenge mode. The first form submission may start the proof automatically. Keep the visible verification control available for manual completion and always verify the resulting token on your server.

The content security policy needs `script-src` and `style-src` permissions for the asset origin, `connect-src` for the provider API origin and `worker-src blob:` to run the bounded proof of work off the UI thread. The widget uses HTTPS or local loopback secure contexts and sends no third-party cookies.

### Application lifecycle

`window.ShieldCaptcha.render(container, { siteKey, action })` returns an instance ID. Bind `execute(id)` to a user gesture to explicitly begin verification, or use the rendered button. The promise resolves to a token or `null`. Read `getResponse(id)` just before an AJAX request, `reset(id)` after a submission attempt, and `remove(id)` when unmounting the container. `mountAll(root)` scans an inserted subtree for declarative widgets. A second render call for the same container returns its existing ID. Use independent containers for separate forms and choose unique response field names for multiple widgets inside one form.

Supported options include `locale` (`en` or `fa`), `theme`, `apiOrigin`, `responseName`, `guardSubmit` and callbacks `onSuccess`, `onError` and `onExpire`. Declarative options can use `data-locale`, `data-theme` and `data-response-name`. Widgets emit `shieldcaptcha:success`, `shieldcaptcha:error` and `shieldcaptcha:expire` events on the container. Keep tokens out of analytics and logs.
