<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Protection alerts</x-slot>
        <x-slot name="description">
            Up to {{ $sampleSize }} recent assessments from the past {{ $window }} minutes.
            A site action appears after {{ $velocityThreshold }} velocity flags or {{ $abuseThreshold }} customer-reported abusive outcomes.
        </x-slot>

        @if ($alerts === [])
            <p class="text-sm text-gray-700 dark:text-gray-300">No actions reached the alert thresholds in this sample.</p>
        @else
            <ul class="space-y-3">
                @foreach (array_slice($alerts, 0, 10) as $alert)
                    <li class="text-sm">
                        <p class="font-medium">{{ $alert['site'] }} · {{ $alert['action'] }} · {{ $alert['environment'] }}</p>
                        <p class="text-gray-700 dark:text-gray-300">
                            {{ $alert['observed'] }} observe-mode velocity flags,
                            {{ $alert['enforced'] }} enforce-mode velocity decisions,
                            {{ $alert['abusive'] }} customer-reported abusive outcomes.
                        </p>
                    </li>
                @endforeach
            </ul>

            @if (count($alerts) > 10)
                <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">Showing 10 of {{ count($alerts) }} flagged site actions.</p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
