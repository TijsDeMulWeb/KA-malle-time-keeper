<?php

use App\Models\Category;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Support\Duration;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public string $description = '';

    public string $projectId = '';

    public string $categoryId = '';

    public function mount(): void
    {
        $this->projectId = (string) $this->defaultProjectId();
    }

    public function start(): void
    {
        $this->authorize('create', TimeEntry::class);

        $this->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'projectId' => ['required', Rule::exists('projects', 'id')->where('user_id', auth()->id())],
            'categoryId' => ['nullable', 'exists:categories,id'],
        ], attributes: [
            'description' => 'omschrijving',
            'projectId' => 'project',
            'categoryId' => 'categorie',
        ]);

        if ($this->running) {
            return;
        }

        $startedAt = now();

        $overlaps = auth()->user()->timeEntries()->overlapping($startedAt, $startedAt->copy()->addMinute())->exists();

        if ($overlaps) {
            $this->addError('description', 'Er is al een registratie die op dit moment loopt.');

            return;
        }

        auth()->user()->timeEntries()->create([
            'project_id' => (int) $this->projectId,
            'category_id' => $this->categoryId !== '' ? (int) $this->categoryId : null,
            'description' => $this->description,
            'started_at' => $startedAt,
        ]);

        $this->description = '';

        unset($this->running);
        $this->dispatch('entries-changed');
    }

    public function stop(): void
    {
        $entry = $this->running;

        if (! $entry) {
            return;
        }

        $this->authorize('update', $entry);

        $entry->update(['ended_at' => max(now(), $entry->started_at->addMinute())]);

        unset($this->running);
        $this->dispatch('entries-changed');
    }

    #[On('entries-changed')]
    public function refresh(): void
    {
        unset($this->running);
    }

    #[Computed]
    public function running(): ?TimeEntry
    {
        return auth()->user()->timeEntries()->running()->with('project')->latest('started_at')->first();
    }

    #[Computed]
    public function projects(): Collection
    {
        return auth()->user()->projects()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('id')->get();
    }

    public function elapsed(): string
    {
        $seconds = $this->running ? max(0, (int) $this->running->started_at->diffInSeconds(now())) : 0;

        return Duration::clockWithSeconds($seconds);
    }

    private function defaultProjectId(): ?int
    {
        $lastUsed = auth()->user()->timeEntries()
            ->whereHas('project', fn ($query) => $query->whereNull('archived_at'))
            ->latest('started_at')
            ->value('project_id');

        return $lastUsed ?? auth()->user()->projects()->active()->orderBy('name')->value('id');
    }
}; ?>

<div wire:poll.30s class="mb-6 grid grid-cols-2 gap-3 border-b border-zinc-200 pb-4 sm:flex sm:flex-wrap sm:items-center dark:border-zinc-700" data-test="timer-bar">
    @if ($this->running)
        <div class="col-span-2 flex min-w-0 items-center gap-3 sm:flex-1">
            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $this->running->project->color }}"></span>
            <div class="min-w-0">
                <div class="truncate font-medium">{{ $this->running->description !== '' ? $this->running->description : __('Zonder omschrijving') }}</div>
                <flux:text size="sm" class="truncate">{{ $this->running->project->name }}</flux:text>
            </div>
        </div>

        <div class="col-span-2 flex items-center gap-3 sm:contents">
            <span class="me-auto font-mono text-2xl tabular-nums sm:me-0" data-test="timer-elapsed" data-timer-started-at="{{ $this->running->started_at->toIso8601String() }}">{{ $this->elapsed() }}</span>

            <flux:button variant="danger" icon="stop" wire:click="stop" data-shortcut="s" data-test="stop-timer">{{ __('Stop') }}</flux:button>

            <flux:button variant="ghost" icon="plus" wire:click="$dispatch('open-entry')" data-shortcut="n" :aria-label="__('Registratie toevoegen')" data-test="add-entry" />
        </div>
    @else
        <div class="col-span-2 sm:min-w-48 sm:flex-1">
            <flux:input wire:model="description" wire:keydown.enter="start" :placeholder="__('Waar werk je aan?')" />
        </div>

        <flux:select wire:model="projectId" class="sm:w-48!" :placeholder="__('Project')">
            @foreach ($this->projects as $project)
                <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="categoryId" class="sm:w-44!" :placeholder="__('Categorie')">
            <flux:select.option value="">{{ __('Geen categorie') }}</flux:select.option>
            @foreach ($this->categories as $category)
                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <div class="col-span-2 flex items-center gap-3 sm:contents">
            <span class="me-auto font-mono text-2xl tabular-nums text-zinc-400 sm:me-0">0:00</span>

            <flux:button variant="primary" icon="play" wire:click="start" data-shortcut="s" data-test="start-timer">{{ __('Start') }}</flux:button>

            <flux:button variant="ghost" icon="plus" wire:click="$dispatch('open-entry')" data-shortcut="n" :aria-label="__('Registratie toevoegen')" data-test="add-entry" />
        </div>
    @endif
</div>
