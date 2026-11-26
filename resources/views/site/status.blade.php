@extends('site.layout')
@section('title', __('site.status').' · '.config('app.name'))
@section('content')
<section class="frame text-page">
    <h1>{{ __('site.status') }}</h1>
    <p class="lede">{{ __($ready ? 'site.ready' : 'site.unavailable') }}</p>
    <a href="{{ route('ready') }}">{{ __('site.readiness_json') }}</a>
</section>
@endsection
