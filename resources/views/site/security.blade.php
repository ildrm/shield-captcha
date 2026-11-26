@extends('site.layout')
@section('title', __('site.security').' · '.config('app.name'))
@section('content')
<article class="frame text-page">
    <h1>{{ __('site.security') }}</h1>
    <p class="lede">{{ __('site.security_intro') }}</p>
    <p>{{ __('site.security_operation') }}</p>
    <p>{{ __('site.security_report') }}</p>
</article>
@endsection
