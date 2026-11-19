<?php

use App\Livewire\Forms\EntryForm;
use App\Models\Category;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\LocalTime;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public EntryForm $form;

    public bool $show = false;

    #[On('open-entry')]
    public function open(?int $entry = null, ?string $date = null, ?string $start = null, ?string $end = null): void
    {
        $this->form->resetErrorBag();

        if ($entry) {
            $timeEntry = auth()->user()->timeEntries()->findOrFail($entry);

            $this->authorize('update', $timeEntry);

            $this->form->fillFrom($timeEntry);
            $this->show = true;

            return;
        }

        $this->authorize('create', TimeEntry::class);

        $startTime = $start ?? LocalTime::now()->startOfHour()->format('H:i');
        $endTime = $end ?? LocalTime::parse('2000-01-01', $startTime)->addHour()->format('H:i');
        $endTime = $endTime === '24:00' ? '23:59' : $endTime;

        $this->form->prepareNew(
            date: $date ?? LocalTime::now()->format('Y-m-d'),
            start: $startTime,
            end: $endTime,
            projectId: (string) $this->defaultProjectId(),
        );

        $this->show = true;
    }

    public function save(): void
    {
        if (! $this->form->save()) {
            return;
        }

        $this->close();
    }

    public function delete(): void
    {
        $entry = $this->form->entry;

        if (! $entry) {
            return;
        }

        $this->authorize('delete', $entry);

        $entry->delete();

        $this->close();
    }

    public function close(): void
    {
        $this->show = false;
        $this->form->reset();

        $this->dispatch('entries-changed');
    }

    #[Computed]
    public function projects(): Collection
    {
        return auth()->user()->projects()
            ->where(function ($query) {
                $query->whereNull('archived_at')
                    ->orWhere('id', $this->form->entry?->project_id);
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('id')->get();
    }

    private function defaultProjectId(): ?int
    {
        $lastUsed = auth()->user()->timeEntries()
            ->whereHas('project', fn ($query) => $query->whereNull('archived_at'))
            ->latest('started_at')
            ->value('project_id');

        return $lastUsed ?? auth()->user()->projects()->whereNull('archived_at')->orderBy('name')->value('id');
    }
}; ?>

<div>
    <flux:modal wire:model.self="show" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-6" data-test="entry-form">
            <flux:heading size="lg">
                {{ $form->entry ? __('Registratie bewerken') : __('Nieuwe registratie') }}
            </flux:heading>

            <flux:input wire:model="form.description" :label="__('Wat heb je gedaan?')" autofocus />

            @if ($this->projects->isEmpty())
                <flux:callout icon="folder" variant="warning">
                    <flux:callout.text>
                        {{ __('Maak eerst een project aan.') }}
                        <flux:callout.link :href="route('projects.index')" wire:navigate>{{ __('Naar projecten') }}</flux:callout.link>
                    </flux:callout.text>
                </flux:callout>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="form.projectId" :label="__('Project')" :placeholder="__('Kies een project')">
                    @foreach ($this->projects as $project)
                        <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.categoryId" :label="__('Categorie')" :placeholder="__('Geen categorie')">
                    <flux:select.option value="">{{ __('Geen categorie') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <flux:input wire:model.live.blur="form.date" type="date" :label="__('Datum')" />
                <flux:input wire:model.live.blur="form.start" type="time" :label="__('Van')" />
                <flux:input wire:model.live.blur="form.end" type="time" :label="__('Tot')" />
            </div>

            <flux:text>
                {{ __('Duur') }}: <span class="font-medium text-zinc-900 dark:text-white">{{ $form->durationLabel() }}</span>
                @if ($form->isRunning())
                    <span class="ms-2 text-sm text-zinc-500">{{ __('(timer loopt nog)') }}</span>
                @endif
            </flux:text>

            <div class="flex items-center gap-3">
                @if ($form->entry)
                    <flux:button
                        type="button"
                        variant="ghost"
                        icon="trash"
                        class="text-red-500!"
                        wire:click="delete"
                        wire:confirm="{{ __('Deze registratie verwijderen?') }}"
                        data-test="delete-entry"
                    />
                @endif

                <flux:spacer />

                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="save-entry">{{ __('Opslaan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
