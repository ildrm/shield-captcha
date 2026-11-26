@if ($items->hasPages())
    <nav class="team-actions" aria-label="{{ $label }}">
        @if ($items->previousPageUrl())
            <a href="{{ $items->previousPageUrl() }}">{{ __('team.previous_page') }}</a>
        @endif
        @if ($items->nextPageUrl())
            <a href="{{ $items->nextPageUrl() }}">{{ __('team.next_page') }}</a>
        @endif
    </nav>
@endif
