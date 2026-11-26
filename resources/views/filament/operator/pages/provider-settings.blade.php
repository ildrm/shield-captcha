<x-filament-panels::page>
    <form class="space-y-6" wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit">
            Save provider settings
        </x-filament::button>
    </form>
</x-filament-panels::page>
