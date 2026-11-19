<?php

use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Vak instellen')] class extends Component {
    public string $periodStartsOn = '';

    public string $periodEndsOn = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->periodStartsOn = $user->period_starts_on?->format('Y-m-d') ?? '';
        $this->periodEndsOn = $user->period_ends_on?->format('Y-m-d') ?? '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'periodStartsOn' => ['nullable', 'date_format:Y-m-d'],
            'periodEndsOn' => ['nullable', 'date_format:Y-m-d', 'after:periodStartsOn'],
        ], attributes: [
            'periodStartsOn' => 'startdatum',
            'periodEndsOn' => 'einddatum',
        ]);

        auth()->user()->update([
            'period_starts_on' => $validated['periodStartsOn'] ?: null,
            'period_ends_on' => $validated['periodEndsOn'] ?: null,
        ]);

        Flux::toast(variant: 'success', text: __('Instellingen opgeslagen.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Vak instellen') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Vak')" :subheading="__('De periode van het vak, voor het rapport Hele vak')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="periodStartsOn" type="date" :label="__('Start van het vak')" />
                <flux:input wire:model="periodEndsOn" type="date" :label="__('Einde van het vak')" />
            </div>

            <flux:button variant="primary" type="submit" data-test="save-time-settings">{{ __('Opslaan') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
