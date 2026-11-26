<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('fa') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('site.description') }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('site.css') }}">
    @stack('head')
</head>
<body>
<a class="skip-link" href="#content">{{ __('site.skip') }}</a>
<header class="site-header">
    <div class="frame header-inner">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">✓</span>{{ config('app.name') }}</a>
        <nav aria-label="{{ __('site.navigation') }}">
            @foreach (['docs', 'playground', 'downloads'] as $item)
                <a href="{{ route($item) }}" @if(request()->routeIs($item)) aria-current="page" @endif>{{ __('site.'.$item) }}</a>
            @endforeach
        </nav>
        <div class="header-controls">
            <form action="{{ route('locale') }}" method="post">
                @csrf
                <button class="locale" name="locale" value="{{ app()->isLocale('fa') ? 'en' : 'fa' }}" lang="{{ app()->isLocale('fa') ? 'en' : 'fa' }}">{{ app()->isLocale('fa') ? 'English' : 'فارسی' }}</button>
            </form>
            <a class="top-login" href="{{ route('filament.customer.auth.login') }}">{{ __('site.login') }}</a>
        </div>
    </div>
</header>
<main id="content">@yield('content')</main>
<footer class="site-footer">
    <div class="frame footer-inner">
        <span>{{ config('app.name') }}</span>
        <nav aria-label="{{ __('site.footer') }}">
            <a href="{{ route('security') }}">{{ __('site.security') }}</a>
            <a href="{{ route('status') }}">{{ __('site.status') }}</a>
        </nav>
    </div>
</footer>
</body>
</html>
