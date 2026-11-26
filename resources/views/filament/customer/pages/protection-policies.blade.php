<x-filament-panels::page>
    <form class="space-y-6" wire:submit="save">
        @if ($publishedVersion !== null)
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ $publishedVersion === 0 ? 'This action uses the built-in policy.' : 'Published revision '.$publishedVersion }}
            </p>
        @endif

        {{ $this->form }}

        <x-filament::button type="submit" wire:loading.attr="disabled">
            Publish policy
        </x-filament::button>
    </form>
</x-filament-panels::page>
