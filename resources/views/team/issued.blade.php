@extends('site.layout')

@section('title', __('team.created').' · '.config('app.name'))

@section('content')
    <div class="frame text-page">
        <a href="{{ route('team.index', ['organization' => $organization->slug]) }}">{{ __('team.back') }}</a>
        <h1>{{ __('team.created') }}</h1>
        <p>{{ __('team.share_code', ['days' => \App\Tenancy\TeamManagement::INVITATION_DAYS]) }}</p>
        <p><a href="{{ route('team-invitations.show') }}">{{ __('team.invitation_page') }}</a></p>
        <pre dir="ltr"><code id="invitation-code">{{ $code }}</code></pre>
        <p>{{ __('team.copy') }}</p>
    </div>
@endsection
