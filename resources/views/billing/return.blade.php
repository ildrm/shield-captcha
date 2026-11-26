@extends('site.layout')

@section('title', __('billing.return_title'))

@section('content')
<section class="frame text-page">
    <h1>{{ __('billing.return_title') }}</h1>
    <p>{{ __('billing.return_body') }}</p>
    <a href="{{ route('filament.customer.auth.login') }}" class="button primary">{{ __('site.login') }}</a>
</section>
@endsection
