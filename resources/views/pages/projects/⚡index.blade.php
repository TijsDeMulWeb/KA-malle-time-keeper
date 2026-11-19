<?php

use App\Livewire\Forms\ProjectForm;
use App\Models\Project;
use App\Support\Duration;
use App\Support\LocalTime;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Projecten')] class extends Component {
    public ProjectForm $form;

    #[Url(as: 'zoek')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $status = 'active';

    public bool $showForm = false;

    public function create(): void
    {
        $this->authorize('create', Project::class);

        $this->form->reset();
        $this->form->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $projectId): void
    {
        $project = Project::availableTo(auth()->user())->findOrFail($projectId);

        $this->authorize('update', $project);

        $this->form->resetErrorBag();
        $this->form->fillFrom($project);
        $this->showForm = true;
    }

    public function save(): void
    {
        if ($this->form->project) {
            $this->authorize('update', $this->form->project);
        }

        $this->form->save();

        $this->showForm = false;
        $this->form->reset();
    }

    public function toggleArchive(int $projectId): void
    {
        $project = Project::availableTo(auth()->user())->findOrFail($projectId);

        $this->authorize('update', $project);

        $project->update(['archived_at' => $project->isArchived() ? null : now()]);
    }

    #[Computed]
    public function projects(): Collection
    {
        $projects = Project::availableTo(auth()->user())
            ->with('schoolClass')
            ->when($this->status === 'archived', fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('client', 'like', "%{$this->search}%");
                });
            })
            ->orderByRaw('user_id is null')
            ->orderBy('name')
            ->get();

        $weekStart = LocalTime::startOfWeek()->utc();
        $weekEnd = $weekStart->addWeek();

        $entries = auth()->user()->timeEntries()
            ->whereIn('project_id', $projects->modelKeys())
            ->get(['project_id', 'started_at', 'ended_at'])
            ->groupBy('project_id');

        return $projects->each(function (Project $project) use ($entries, $weekStart, $weekEnd) {
            $projectEntries = $entries->get($project->id, collect());

            $project->total_minutes = $projectEntries->sum(fn ($entry) => $entry->durationInMinutes());
            $project->week_minutes = $projectEntries->sum(fn ($entry) => $entry->minutesWithin($weekStart, $weekEnd));
        });
    }
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Projecten') }}</flux:heading>
            <flux:subheading>{{ __('De projecten waar jij aan werkt.') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Zoeken')" class="w-44" />

            <flux:radio.group wire:model.live="status" variant="segmented">
                <flux:radio value="active" :label="__('Actief')" />
                <flux:radio value="archived" :label="__('Gearchiveerd')" />
            </flux:radio.group>

            <flux:button variant="primary" icon="plus" wire:click="create" data-test="new-project">
                {{ __('Nieuw project') }}
            </flux:button>
        </div>
    </div>

    @if ($this->projects->isEmpty())
        <flux:callout icon="folder">
            <flux:callout.heading>{{ $search !== '' ? __('Geen projecten gevonden') : __('Nog geen projecten') }}</flux:callout.heading>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->projects as $project)
            <div
                wire:key="project-{{ $project->id }}"
                class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
                data-test="project-card"
            >
                <div class="h-1" style="background-color: {{ $project->color }}"></div>

                <div class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <flux:heading size="lg" class="truncate">{{ $project->name }}</flux:heading>
                            <flux:text size="sm">{{ $project->client ?? __('Geen klant') }}</flux:text>
                        </div>

                        @if($project->isShared())
                            <flux:badge size="sm" icon="users" class="ms-auto shrink-0" data-test="shared-badge">{{ $project->schoolClass?->name ?? __('Gedeeld') }}</flux:badge>
                        @endif

                        @can('update', $project)
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset />

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $project->id }})">{{ __('Bewerken') }}</flux:menu.item>
                                <flux:menu.item icon="archive-box" wire:click="toggleArchive({{ $project->id }})">
                                    {{ $project->isArchived() ? __('Dearchiveren') : __('Archiveren') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                        @endcan
                    </div>

                    <div>
                        <div class="text-3xl font-semibold tracking-tight">{{ Duration::format($project->total_minutes) }}</div>
                        <flux:text size="sm">
                            {{ __('totaal') }} · {{ Duration::format($project->week_minutes) }} {{ __('deze week') }}
                        </flux:text>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <flux:modal wire:model.self="showForm" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-6" data-test="project-form">
            <flux:heading size="lg">{{ $form->project ? __('Project bewerken') : __('Nieuw project') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Naam')" autofocus />
            <flux:input wire:model="form.client" :label="__('Klant')" :placeholder="__('Optioneel')" />

            <flux:field>
                <flux:label>{{ __('Kleur') }}</flux:label>
                <input wire:model="form.color" type="color" class="h-10 w-20 cursor-pointer rounded-md border border-zinc-200 bg-transparent p-1 dark:border-zinc-700" />
                <flux:error name="form.color" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-project">{{ __('Opslaan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
