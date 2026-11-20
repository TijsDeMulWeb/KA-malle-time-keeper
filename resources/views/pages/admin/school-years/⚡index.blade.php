<?php

use App\Livewire\Forms\SchoolYearForm;
use App\Models\SchoolYear;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Schooljaren')] class extends Component {
    public SchoolYearForm $form;

    public bool $showForm = false;

    public function create(): void
    {
        $this->authorize('create', SchoolYear::class);

        $this->form->resetErrorBag();
        $this->form->prepareNew();
        $this->showForm = true;
    }

    public function edit(int $schoolYearId): void
    {
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $this->authorize('update', $schoolYear);

        $this->form->resetErrorBag();
        $this->form->fillFrom($schoolYear);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->form->schoolYear ? 'update' : 'create', $this->form->schoolYear ?? SchoolYear::class);

        if (! $this->form->save()) {
            return;
        }

        $this->showForm = false;
        $this->form->reset();
    }

    public function delete(int $schoolYearId): void
    {
        $schoolYear = SchoolYear::findOrFail($schoolYearId);

        $this->authorize('delete', $schoolYear);

        $schoolYear->delete();

        Flux::toast(text: __('Schooljaar verwijderd.'));
    }

    #[Computed]
    public function schoolYears(): Collection
    {
        return SchoolYear::withCount('schoolClasses')
            ->orderByDesc('starts_on')
            ->get()
            ->each(function (SchoolYear $schoolYear) {
                $schoolYear->students_count = User::whereHas(
                    'schoolClasses',
                    fn ($query) => $query->where('school_year_id', $schoolYear->id),
                )->count();
            });
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Schooljaren') }}</flux:heading>
            <flux:subheading>{{ __('Elk schooljaar heeft klassen. Een afgesloten jaar blijft bewaard, maar kan niet meer gewijzigd worden.') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="create" data-test="new-school-year">{{ __('Nieuw schooljaar') }}</flux:button>
    </div>

    @if($this->schoolYears->isEmpty())
        <flux:callout icon="academic-cap">
            <flux:callout.heading>{{ __('Nog geen schooljaren') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Maak een schooljaar aan, voeg klassen toe en nodig daarna studenten uit.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($this->schoolYears as $schoolYear)
            <div wire:key="school-year-{{ $schoolYear->id }}" class="relative rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900" data-test="school-year-card">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('admin.school-years.show', $schoolYear) }}" wire:navigate class="after:absolute after:inset-0">
                            <flux:heading size="lg">{{ $schoolYear->name }}</flux:heading>
                        </a>
                        <flux:text size="sm">{{ $schoolYear->starts_on->translatedFormat('j M Y') }} tot {{ $schoolYear->ends_on->translatedFormat('j M Y') }}</flux:text>
                    </div>

                    <div class="relative flex items-center gap-1">
                        @if($schoolYear->isClosed())
                            <flux:badge size="sm" icon="lock-closed">{{ __('Afgesloten') }}</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">{{ __('Open') }}</flux:badge>
                        @endif

                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset />

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $schoolYear->id }})">{{ __('Bewerken') }}</flux:menu.item>
                                @if($schoolYear->school_classes_count === 0)
                                    <flux:menu.item icon="trash" variant="danger" x-on:click="$dispatch('confirm-action', { message: @js(__('Dit schooljaar verwijderen?')), confirm: () => $wire.delete({{ $schoolYear->id }}) })">{{ __('Verwijderen') }}</flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>

                <div class="mt-4 flex gap-6 text-sm">
                    <div><span class="text-lg font-semibold">{{ $schoolYear->school_classes_count }}</span> <span class="text-zinc-500">{{ trans_choice('klas|klassen', $schoolYear->school_classes_count) }}</span></div>
                    <div><span class="text-lg font-semibold">{{ $schoolYear->students_count }}</span> <span class="text-zinc-500">{{ trans_choice('student|studenten', $schoolYear->students_count) }}</span></div>
                </div>
            </div>
        @endforeach
    </div>

    <flux:modal wire:model.self="showForm" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-6" data-test="school-year-form">
            <flux:heading size="lg">{{ $form->schoolYear ? __('Schooljaar bewerken') : __('Nieuw schooljaar') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Naam')" placeholder="2026-2027" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="form.startsOn" type="date" :label="__('Van')" />
                <flux:input wire:model="form.endsOn" type="date" :label="__('Tot en met')" />
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-school-year">{{ __('Opslaan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
