<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="flex flex-col lg:h-dvh">
        <livewire:timer-bar />

        <div class="flex min-h-0 flex-1 flex-col lg:overflow-y-auto">
            {{ $slot }}
        </div>

        <livewire:entry-modal />

        <x-shortcuts-modal />
    </flux:main>
</x-layouts::app.sidebar>
