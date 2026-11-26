@extends('site.layout')

@section('content')
<section class="hero frame">
    <div>
        <h1>{{ __('site.hero') }}</h1>
        <p class="lede">{{ __('site.description') }}</p>
        <div class="actions">
            <a class="button primary" href="{{ route('filament.customer.auth.register') }}">{{ __('site.create') }}</a>
            <a class="button outline" href="{{ route('docs') }}">{{ __('site.quickstart') }} <span aria-hidden="true">↗</span></a>
        </div>
    </div>
    <div class="flow">
        <h2>{{ __('site.flow') }}</h2>
        <ol>
            <li><div class="flow-icon" aria-hidden="true">☑</div><div><strong>{{ __('site.challenge') }}</strong><p>{{ __('site.challenge_text') }}</p></div></li>
            <li><div class="flow-icon" aria-hidden="true">⌘</div><div><strong>{{ __('site.verify') }}</strong><p>{{ __('site.verify_text') }}</p></div></li>
            <li><div class="flow-icon" aria-hidden="true">✓</div><div><strong>{{ __('site.enforce') }}</strong><p>{{ __('site.enforce_text') }}</p></div></li>
        </ol>
        <a href="{{ route('playground') }}">{{ __('site.try_sandbox') }} <span aria-hidden="true">↗</span></a>
    </div>
</section>
<section class="features">
    <div class="frame features-inner">
        <article><h2>{{ __('site.captcha') }}</h2><p>{{ __('site.captcha_text') }}</p></article>
        <article><h2>{{ __('site.sms') }}</h2><p>{{ __('site.sms_text') }}</p></article>
        <article><h2>{{ __('site.host') }}</h2><p>{{ __('site.host_text') }}</p></article>
    </div>
</section>
@endsection
