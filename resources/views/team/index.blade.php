@extends('site.layout')

@section('title', __('team.title').' · '.config('app.name'))
@include('team.styles')

@section('content')
    <div class="frame text-page">
        <a href="{{ route('filament.customer.pages.dashboard', ['tenant' => $organization->slug]) }}">{{ __('team.dashboard') }}</a>
        <h1>{{ __('team.title') }}</h1>
        <p class="lede">{{ $organization->name }}</p>

        @if (session('team_status'))
            <p role="status">{{ session('team_status') }}</p>
        @endif
        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <section class="artifact" aria-labelledby="invite-heading">
            <h2 id="invite-heading">{{ __('team.invite') }}</h2>
            <p>{{ __('team.invite_help') }}</p>
            <form class="team-form" action="{{ route('team.issue', ['organization' => $organization->slug]) }}" method="post">
                @csrf
                <label>
                    {{ __('team.email') }}
                    <input name="email" type="email" value="{{ old('email') }}" maxlength="254" autocomplete="off" dir="ltr" required>
                </label>
                <label>
                    {{ __('team.role') }}
                    <select name="role" required>
                        @foreach (array_reverse(array_keys($roles)) as $value)
                            <option value="{{ $value }}" @selected(old('role') === $value)>{{ __('team.roles.'.$value) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button primary" type="submit">{{ __('team.create') }}</button>
            </form>
        </section>

        <section class="artifact" aria-labelledby="members-heading">
            <h2 id="members-heading">{{ __('team.members') }}</h2>
            <ul class="team-list">
                @foreach ($members as $member)
                    @php($role = $member->pivot->role)
                    <li>
                        <strong><bdi>{{ $member->name }}</bdi></strong>
                        <p><bdi>{{ $member->email }}</bdi> · {{ isset(\App\Tenancy\TeamManagement::ROLES[$role]) ? __('team.roles.'.$role) : $role }}{{ $member->is($actor) ? ' · '.__('team.you') : '' }}</p>
                        @if (! $member->is($actor) && isset($roles[$role]))
                            <div class="team-actions">
                                <form class="team-form" action="{{ route('team.update-role', ['organization' => $organization->slug, 'member' => $member->id]) }}" method="post">
                                    @csrf
                                    @method('PATCH')
                                    <label>
                                        {{ __('team.member_role', ['name' => $member->name]) }}
                                        <select name="role" required>
                                            @foreach (array_keys($roles) as $value)
                                                <option value="{{ $value }}" @selected($value === $role)>{{ __('team.roles.'.$value) }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button class="button outline" type="submit">{{ __('team.save_role') }}</button>
                                </form>
                                <form action="{{ route('team.remove', ['organization' => $organization->slug, 'member' => $member->id]) }}" method="post">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button outline" type="submit" aria-label="{{ __('team.remove_member', ['name' => $member->name]) }}">{{ __('team.remove') }}</button>
                                </form>
                            </div>
                        @endif
                        @if ($canTransfer && ! $member->is($actor) && \App\Tenancy\TeamManagement::canAccept($member))
                            <details>
                                <summary>{{ __('team.transfer', ['name' => $member->name]) }}</summary>
                                <p>{{ __('team.transfer_help') }}</p>
                                <form class="team-form" action="{{ route('team.transfer', ['organization' => $organization->slug]) }}" method="post">
                                    @csrf
                                    <input type="hidden" name="member" value="{{ $member->id }}">
                                    <label>
                                        {{ __('team.current_password') }}
                                        <input type="password" name="current_password" maxlength="512" autocomplete="current-password" required>
                                    </label>
                                    <button class="button outline" type="submit">{{ __('team.confirm_transfer') }}</button>
                                </form>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
            @include('team.pagination', ['items' => $members, 'label' => __('team.member_pages')])
        </section>

        <section class="artifact" aria-labelledby="invitations-heading">
            <h2 id="invitations-heading">{{ __('team.pending') }}</h2>
            <ul class="team-list">
                @forelse ($invitations as $invitation)
                    <li>
                        <strong><bdi>{{ $invitation->email }}</bdi></strong>
                        <p>{{ __('team.roles.'.$invitation->role) }} · {{ __('team.expires', ['timestamp' => $invitation->expires_at->utc()->format('Y-m-d H:i')]) }}</p>
                        @if (isset($roles[$invitation->role]))
                            <form action="{{ route('team.revoke', ['organization' => $organization->slug, 'invitation' => $invitation->id]) }}" method="post">
                                @csrf
                                @method('DELETE')
                                <button class="button outline" type="submit" aria-label="{{ __('team.revoke_invitation', ['email' => $invitation->email]) }}">{{ __('team.revoke') }}</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li>{{ __('team.no_invitations') }}</li>
                @endforelse
            </ul>
            @include('team.pagination', ['items' => $invitations, 'label' => __('team.invitation_pages')])
        </section>

        <section class="artifact" aria-labelledby="audit-heading">
            <h2 id="audit-heading">{{ __('team.audit') }}</h2>
            @if ($canTransfer)
                <p><a href="{{ route('team.export', ['organization' => $organization->slug]) }}">{{ __('team.download') }}</a></p>
            @endif
            <ul class="team-list">
                @forelse ($audit as $entry)
                    <li>
                        <strong><bdi>{{ $entry->operation }}</bdi></strong>
                        <p><bdi>{{ \Illuminate\Support\Carbon::parse($entry->created_at)->utc()->format('Y-m-d H:i') }} UTC</bdi>{{ $entry->actor_id ? ' · '.__('team.actor', ['id' => $entry->actor_id]) : '' }}{{ $entry->site_id ? ' · '.__('team.site', ['id' => $entry->site_id]) : '' }}</p>
                    </li>
                @empty
                    <li>{{ __('team.no_audit') }}</li>
                @endforelse
            </ul>
            @include('team.pagination', ['items' => $audit, 'label' => __('team.audit_pages')])
        </section>
    </div>
@endsection
