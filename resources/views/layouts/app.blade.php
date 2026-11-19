<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <livewire:timer-bar />

        {{ $slot }}

        <livewire:entry-modal />

        <x-shortcuts-modal />
    </flux:main>
</x-layouts::app.sidebar>
