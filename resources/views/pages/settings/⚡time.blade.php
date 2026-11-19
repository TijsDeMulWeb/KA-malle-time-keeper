<?php

use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Uren instellen')] class extends Component {
    public string $weeklyGoalHours = '';

    public string $periodStartsOn = '';

    public string $periodEndsOn = '';

    public string $periodGoalHours = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->weeklyGoalHours = (string) ($user->weekly_goal_minutes / 60);
        $this->periodStartsOn = $user->period_starts_on?->format('Y-m-d') ?? '';
        $this->periodEndsOn = $user->period_ends_on?->format('Y-m-d') ?? '';
        $this->periodGoalHours = $user->period_goal_minutes ? (string) ($user->period_goal_minutes / 60) : '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'weeklyGoalHours' => ['required', 'numeric', 'min:0', 'max:168'],
            'periodStartsOn' => ['nullable', 'date_format:Y-m-d'],
            'periodEndsOn' => ['nullable', 'date_format:Y-m-d', 'after:periodStartsOn'],
            'periodGoalHours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ], attributes: [
            'weeklyGoalHours' => 'weekdoel',
            'periodStartsOn' => 'startdatum',
            'periodEndsOn' => 'einddatum',
            'periodGoalHours' => 'urendoel',
        ]);

        auth()->user()->update([
            'weekly_goal_minutes' => (int) round($validated['weeklyGoalHours'] * 60),
            'period_starts_on' => $validated['periodStartsOn'] ?: null,
            'period_ends_on' => $validated['periodEndsOn'] ?: null,
            'period_goal_minutes' => $validated['periodGoalHours'] !== null && $validated['periodGoalHours'] !== ''
                ? (int) round($validated['periodGoalHours'] * 60)
                : null,
        ]);

        Flux::toast(variant: 'success', text: __('Instellingen opgeslagen.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Uren instellen') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Uren')" :subheading="__('Je weekdoel en de periode van het vak')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <flux:input wire:model="weeklyGoalHours" type="number" step="0.25" min="0" :label="__('Weekdoel (uren)')" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="periodStartsOn" type="date" :label="__('Start van het vak')" />
                <flux:input wire:model="periodEndsOn" type="date" :label="__('Einde van het vak')" />
            </div>

            <flux:input wire:model="periodGoalHours" type="number" step="0.25" min="0" :label="__('Urendoel voor het hele vak')" />

            <flux:button variant="primary" type="submit" data-test="save-time-settings">{{ __('Opslaan') }}</flux:button>
        </form>
    </x-pages::settings.layout>
</section>
