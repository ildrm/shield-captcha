@extends('site.layout')

@section('title', __('team.accept').' · '.config('app.name'))
@include('team.styles')

@section('content')
    <div class="frame text-page">
        <h1>{{ __('team.accept') }}</h1>
        <p class="lede">{{ __('team.accept_help') }}</p>
        @if ($invalid ?? false)
            <p role="alert">{{ __('team.invalid') }}</p>
        @endif
        <form class="artifact team-form" action="{{ route('team-invitations.accept') }}" method="post">
            @csrf
            <label>
                {{ __('team.code') }}
                <input name="invitation_code" type="password" maxlength="64" autocomplete="off" autocapitalize="off" spellcheck="false" dir="ltr" required>
            </label>
            <button class="button primary" type="submit">{{ __('team.join') }}</button>
        </form>
    </div>
@endsection
