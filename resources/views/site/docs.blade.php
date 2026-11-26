@extends('site.layout')
@section('title', __('site.quickstart').' · '.config('app.name'))
@section('content')
<article class="frame text-page">
    <h1>{{ __('site.quickstart') }}</h1>
    <p class="lede">{{ __('site.docs_intro') }}</p>
    <ol class="guide">
        <li><h2>{{ __('site.onboard_title') }}</h2><p>{{ __('site.onboard') }}</p><a href="{{ route('filament.customer.auth.login') }}">{{ __('site.login') }}</a></li>
        <li><h2>{{ __('site.embed_title') }}</h2><p>{{ __('site.embed') }}</p>
            <pre dir="ltr"><code>&lt;script src="{{ url('/assets/captcha/v1/widget.js') }}" defer&gt;&lt;/script&gt;
&lt;div data-shield-captcha data-site-key="YOUR_PUBLIC_SITE_KEY"
     data-action="contact" data-mode="managed" data-locale="{{ app()->getLocale() }}"&gt;&lt;/div&gt;</code></pre>
            <p>{{ __('site.managed_mode') }}</p>
        </li>
        <li><h2>{{ __('site.verify') }}</h2><p>{{ __('site.backend') }}</p>
            <pre dir="ltr"><code>POST /api/v1/verify
Authorization: Bearer YOUR_SERVER_SECRET
Content-Type: application/json

{
  "token": "VALUE_FROM_FORM",
  "site_key": "YOUR_PUBLIC_SITE_KEY",
  "expected_action": "contact",
  "expected_origin": "https://your-site.example",
  "expected_environment": "production"
}</code></pre>
        </li>
    </ol>
    <section id="sms">
        <h2>{{ __('site.sms') }}</h2>
        <p>{{ __('site.sms_docs') }}</p>
        <pre dir="ltr"><code>POST /api/v1/sms/assess
Authorization: Bearer YOUR_ASSESSMENT_SECRET
Content-Type: application/json

{
  "site_key": "YOUR_PUBLIC_SITE_KEY",
  "action": "otp_send",
  "event_id": "YOUR_LOGICAL_SEND_ID",
  "destination_hash": "YOUR_64_HEX_HMAC_SHA256"
}</code></pre>
        <h2>{{ __('site.sms_delivery') }}</h2>
        <p>{{ __('site.sms_delivery_docs') }}</p>
        <pre dir="ltr"><code>POST /api/v1/sms/send
Authorization: Bearer YOUR_SMS_SEND_SECRET
Content-Type: application/json

{
  "site_key": "YOUR_PRODUCTION_SITE_KEY",
  "action": "otp_send",
  "event_id": "YOUR_LOGICAL_SEND_ID",
  "destination": "YOUR_E164_MOBILE_NUMBER",
  "otp": "YOUR_NUMERIC_OTP"
}</code></pre>
    </section>
    <div class="actions">
        <a class="button outline" href="{{ route('api-reference') }}">{{ __('site.openapi') }}</a>
        <a class="button outline" href="{{ route('downloads') }}">{{ __('site.downloads') }}</a>
    </div>
</article>
@endsection
