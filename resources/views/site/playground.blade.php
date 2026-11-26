@extends('site.layout')
@section('title', __('site.playground').' · '.config('app.name'))
@if (filled($settings['site_key'] ?? null) && filled($settings['action'] ?? null))
    @push('head')
        <script src="{{ asset('assets/captcha/v1/widget.js') }}" defer></script>
    @endpush
@endif
@section('content')
<section class="frame text-page">
    <h1>{{ __('site.playground') }}</h1>
    <p class="lede">{{ __('site.playground_intro', ['origin' => request()->getSchemeAndHttpHost()]) }}</p>
    @if ($invalid)<p role="alert">{{ __('site.invalid_playground') }}</p>@endif
    <form action="{{ route('playground') }}" method="get" class="settings">
        <label>{{ __('site.public_key') }}<input name="site_key" dir="ltr" value="{{ $settings['site_key'] ?? '' }}" pattern="site_test_[a-f0-9]{32}" maxlength="42" required></label>
        <label>{{ __('site.action') }}<input name="action" dir="ltr" value="{{ $settings['action'] ?? 'contact' }}" pattern="[a-z][a-z0-9_\x2d]{0,63}" maxlength="64" required></label>
        <button class="button primary">{{ __('site.load_widget') }}</button>
    </form>
    @if (filled($settings['site_key'] ?? null) && filled($settings['action'] ?? null))
        <div class="demo">
            <div data-shield-captcha data-site-key="{{ $settings['site_key'] }}" data-action="{{ $settings['action'] }}" data-locale="{{ app()->getLocale() }}"></div>
            <noscript>{{ __('site.needs_js') }}</noscript>
        </div>
    @endif
    <p>{{ __('site.playground_next') }}</p>
    <a href="{{ route('docs') }}">{{ __('site.quickstart') }}</a>
</section>
@endsection
