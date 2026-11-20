<?php

use App\Actions\AssignStudentToClass;
use App\Actions\InviteStudents;
use App\Actions\SendInvitation;
use App\Enums\Role;
use App\Livewire\Forms\BulkInviteForm;
use App\Livewire\Forms\ProjectForm;
use App\Models\Project;
use App\Models\SchoolClass;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Duration;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Klas')] class extends Component {
    #[Locked]
    public SchoolClass $schoolClass;

    public BulkInviteForm $bulkForm;

    public ProjectForm $projectForm;

    public bool $showInviteForm = false;

    public bool $showAddExisting = false;

    public bool $showTransfer = false;

    public bool $showProjectForm = false;

    public string $studentSearch = '';

    /** @var array<int, string> */
    public array $selectedStudentIds = [];

    public string $targetClassId = '';

    public function mount(SchoolClass $schoolClass): void
    {
        $this->authorize('view', $schoolClass);

        $this->schoolClass = $schoolClass->load('schoolYear');
    }

    public function openInviteForm(): void
    {
        $this->authorize('update', $this->schoolClass);
        $this->authorize('create', User::class);

        $this->bulkForm->reset();
        $this->bulkForm->resetErrorBag();
        $this->showInviteForm = true;
    }

    public function invite(): void
    {
        $this->authorize('update', $this->schoolClass);
        $this->authorize('create', User::class);

        $students = $this->bulkForm->students();

        if ($students === null) {
            return;
        }

        $failed = app(InviteStudents::class)->execute($students, $this->schoolClass);

        $this->showInviteForm = false;
        $this->bulkForm->reset();

        if ($failed->isNotEmpty()) {
            Flux::toast(variant: 'danger', text: __('Uitnodiging niet verstuurd naar: :emails', ['emails' => $failed->implode(', ')]));

            return;
        }

        Flux::toast(variant: 'success', text: trans_choice(':count uitnodiging verstuurd.|:count uitnodigingen verstuurd.', $students->count()));
    }

    public function openAddExisting(): void
    {
        $this->authorize('update', $this->schoolClass);

        $this->reset('studentSearch', 'selectedStudentIds');
        $this->showAddExisting = true;
    }

    public function addExisting(AssignStudentToClass $assignStudentToClass): void
    {
        $this->authorize('update', $this->schoolClass);

        $students = User::role(Role::Student->value)
            ->whereKey(array_map('intval', $this->selectedStudentIds))
            ->get();

        $students->each(fn (User $student) => $assignStudentToClass->execute($student, $this->schoolClass));

        $this->showAddExisting = false;
        $this->reset('studentSearch', 'selectedStudentIds');

        Flux::toast(variant: 'success', text: trans_choice(':count student toegevoegd.|:count studenten toegevoegd.', $students->count()));
    }

    public function removeStudent(int $userId): void
    {
        $this->authorize('update', $this->schoolClass);

        $this->schoolClass->students()->detach($userId);
    }

    public function resendInvitation(int $userId, SendInvitation $sendInvitation): void
    {
        $student = $this->schoolClass->students()->findOrFail($userId);

        $this->authorize('update', $student);

        if ($student->hasAcceptedInvitation()) {
            return;
        }

        $sent = $sendInvitation->execute($student);

        Flux::toast(
            variant: $sent ? 'success' : 'danger',
            text: $sent ? __('Uitnodiging verstuurd naar :email.', ['email' => $student->email]) : __('De uitnodiging kon niet verstuurd worden.'),
        );
    }

    public function openTransfer(): void
    {
        $this->authorize('view', $this->schoolClass);

        $this->targetClassId = (string) ($this->transferTargets->first()?->id ?? '');
        $this->resetErrorBag();
        $this->showTransfer = true;
    }

    public function transfer(AssignStudentToClass $assignStudentToClass): void
    {
        $target = $this->transferTargets->firstWhere('id', (int) $this->targetClassId);

        if (! $target) {
            $this->addError('targetClassId', __('Kies een klas.'));

            return;
        }

        $this->authorize('update', $target);

        $students = $this->schoolClass->students()->role(Role::Student->value)->get();

        $students->each(fn (User $student) => $assignStudentToClass->execute($student, $target));

        $this->showTransfer = false;

        Flux::toast(variant: 'success', text: __(':count studenten toegevoegd aan :class.', ['count' => $students->count(), 'class' => $target->fullName()]));
    }

    public function createProject(): void
    {
        $this->authorize('update', $this->schoolClass);
        $this->authorize('createShared', Project::class);

        $this->projectForm->reset();
        $this->projectForm->resetErrorBag();
        $this->projectForm->schoolClass = $this->schoolClass;
        $this->showProjectForm = true;
    }

    public function editProject(int $projectId): void
    {
        $project = $this->schoolClass->projects()->findOrFail($projectId);

        $this->authorize('update', $project);

        $this->projectForm->resetErrorBag();
        $this->projectForm->fillFrom($project);
        $this->showProjectForm = true;
    }

    public function saveProject(): void
    {
        $this->authorize('update', $this->schoolClass);

        $this->projectForm->project
            ? $this->authorize('update', $this->projectForm->project)
            : $this->authorize('createShared', Project::class);

        $this->projectForm->schoolClass = $this->schoolClass;
        $this->projectForm->save();

        $this->showProjectForm = false;
        $this->projectForm->reset();
    }

    public function toggleArchiveProject(int $projectId): void
    {
        $project = $this->schoolClass->projects()->findOrFail($projectId);

        $this->authorize('update', $project);

        $project->update(['archived_at' => $project->isArchived() ? null : now()]);
    }

    #[Computed]
    public function students(): Collection
    {
        $students = $this->schoolClass->students()->orderBy('name')->get();

        $entries = TimeEntry::whereIn('user_id', $students->modelKeys())
            ->startingBetween($this->schoolClass->schoolYear->startsAt()->utc(), $this->schoolClass->schoolYear->endsAt()->utc())
            ->get(['user_id', 'started_at', 'ended_at'])
            ->groupBy('user_id');

        return $students->each(function (User $student) use ($entries) {
            $student->year_minutes = $entries->get($student->id, collect())
                ->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
        });
    }

    #[Computed]
    public function projects(): Collection
    {
        $projects = $this->schoolClass->projects()->orderByRaw('archived_at is not null')->orderBy('name')->get();

        $entries = TimeEntry::whereIn('project_id', $projects->modelKeys())
            ->get(['project_id', 'started_at', 'ended_at'])
            ->groupBy('project_id');

        return $projects->each(function (Project $project) use ($entries) {
            $project->total_minutes = $entries->get($project->id, collect())
                ->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
        });
    }

    #[Computed]
    public function studentProjects(): Collection
    {
        $yearStart = $this->schoolClass->schoolYear->startsAt()->utc();
        $yearEnd = $this->schoolClass->schoolYear->endsAt()->utc();

        $projects = Project::whereIn('user_id', $this->students->modelKeys())
            ->where(function ($query) use ($yearStart, $yearEnd) {
                $query->whereHas('timeEntries', fn ($query) => $query->startingBetween($yearStart, $yearEnd))
                    ->orWhereBetween('created_at', [$yearStart, $yearEnd]);
            })
            ->with('user')
            ->get();

        $entries = TimeEntry::whereIn('project_id', $projects->modelKeys())
            ->startingBetween($yearStart, $yearEnd)
            ->get(['project_id', 'started_at', 'ended_at'])
            ->groupBy('project_id');

        return $projects
            ->each(function (Project $project) use ($entries) {
                $project->year_minutes = $entries->get($project->id, collect())
                    ->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
            })
            ->sortBy(fn (Project $project) => [$project->user->name, $project->name])
            ->values();
    }

    #[Computed]
    public function availableStudents(): Collection
    {
        return User::role(Role::Student->value)
            ->whereDoesntHave('schoolClasses', fn ($query) => $query->whereKey($this->schoolClass->id))
            ->with('currentSchoolClasses.schoolYear')
            ->when($this->studentSearch !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->studentSearch}%")
                        ->orWhere('email', 'like', "%{$this->studentSearch}%");
                });
            })
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function transferTargets(): Collection
    {
        return SchoolClass::inOpenYear()
            ->where('school_year_id', '!=', $this->schoolClass->school_year_id)
            ->with('schoolYear')
            ->get()
            ->sortBy(fn (SchoolClass $schoolClass) => [$schoolClass->schoolYear->starts_on, $schoolClass->name])
            ->values();
    }

    public function isEditable(): bool
    {
        return ! $this->schoolClass->isClosed();
    }
}; ?>

<div class="mx-auto w-full max-w-5xl space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:link :href="route('admin.school-years.show', $schoolClass->schoolYear)" variant="subtle" class="text-sm" wire:navigate>&larr; {{ $schoolClass->schoolYear->name }}</flux:link>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ __('Klas :name', ['name' => $schoolClass->name]) }}</flux:heading>
                @if($schoolClass->isClosed())
                    <flux:badge size="sm" icon="lock-closed">{{ __('Afgesloten') }}</flux:badge>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            @if($this->students->isNotEmpty())
                <flux:button icon="arrow-right-circle" wire:click="openTransfer" data-test="transfer-students">{{ __('Overzetten naar ander jaar') }}</flux:button>
            @endif
            @if($this->isEditable())
                <flux:button icon="user-plus" wire:click="openAddExisting" data-test="add-existing-students">{{ __('Bestaande studenten') }}</flux:button>
                <flux:button variant="primary" icon="envelope" wire:click="openInviteForm" data-test="invite-students">{{ __('Studenten uitnodigen') }}</flux:button>
            @endif
        </div>
    </div>

    <section class="space-y-3">
        <flux:heading size="lg">{{ __('Studenten') }} <span class="text-zinc-500">({{ $this->students->count() }})</span></flux:heading>

        @if($this->students->isEmpty())
            <flux:callout icon="users">
                <flux:callout.text>{{ __('Nog geen studenten in deze klas.') }}</flux:callout.text>
            </flux:callout>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Naam') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end" class="max-sm:hidden">{{ __('Uren dit schooljaar') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->students as $student)
                        <flux:table.row :key="'student-'.$student->id" data-test="student-row">
                            <flux:table.cell>
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $student->name }}</div>
                                    <div class="truncate text-xs text-zinc-500">{{ $student->email }}</div>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if($student->hasAcceptedInvitation())
                                    <flux:badge size="sm" color="green" inset="top bottom">{{ __('Actief') }}</flux:badge>
                                @elseif($student->invitationHasExpired())
                                    <flux:badge size="sm" color="red" inset="top bottom">{{ __('Uitnodiging verlopen') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="amber" inset="top bottom">{{ __('Uitgenodigd') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums max-sm:hidden">{{ Duration::format($student->year_minutes) }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:dropdown align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                                    <flux:menu>
                                        <flux:menu.item icon="chart-bar" :href="route('admin.users.show', ['user' => $student, 'periode' => 'year', 'jaar' => $schoolClass->school_year_id])" wire:navigate>{{ __('Rapport bekijken') }}</flux:menu.item>
                                        <x-impersonate-menu-item :user="$student">{{ __('Bekijk als student') }}</x-impersonate-menu-item>
                                        @unless($student->hasAcceptedInvitation())
                                            <flux:menu.item icon="envelope" wire:click="resendInvitation({{ $student->id }})">{{ __('Uitnodiging opnieuw sturen') }}</flux:menu.item>
                                        @endunless
                                        @if($this->isEditable())
                                            <flux:menu.separator />
                                            <flux:menu.item icon="user-minus" variant="danger" x-on:click="$dispatch('confirm-action', { message: @js(__('Uit deze klas verwijderen? Het account en de uren blijven bestaan.')), confirm: () => $wire.removeStudent({{ $student->id }}) })">{{ __('Uit klas verwijderen') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">{{ __('Projecten van de klas') }}</flux:heading>
                <flux:text size="sm">{{ __('Iedereen in deze klas kan op deze projecten uren schrijven.') }}</flux:text>
            </div>
            @if($this->isEditable())
                <flux:button size="sm" icon="plus" wire:click="createProject" data-test="new-class-project">{{ __('Nieuw project') }}</flux:button>
            @endif
        </div>

        <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @forelse($this->projects as $project)
                <div wire:key="class-project-{{ $project->id }}" class="flex items-center gap-3 px-4 py-3" data-test="class-project-row">
                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $project->color }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium {{ $project->isArchived() ? 'text-zinc-500 line-through' : '' }}">{{ $project->name }}</div>
                        <flux:text size="sm">{{ $project->client ?? __('Geen klant') }} · {{ Duration::format($project->total_minutes) }}</flux:text>
                    </div>

                    @if($this->isEditable())
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset />

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="editProject({{ $project->id }})">{{ __('Bewerken') }}</flux:menu.item>
                                <flux:menu.item icon="archive-box" wire:click="toggleArchiveProject({{ $project->id }})">
                                    {{ $project->isArchived() ? __('Dearchiveren') : __('Archiveren') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    @endif
                </div>
            @empty
                <div class="p-4"><flux:text>{{ __('Nog geen projecten voor deze klas.') }}</flux:text></div>
            @endforelse
        </div>
    </section>

    <section class="space-y-3">
        <div>
            <flux:heading size="lg">{{ __('Eigen projecten van studenten') }} <span class="text-zinc-500">({{ $this->studentProjects->count() }})</span></flux:heading>
            <flux:text size="sm">{{ __('Projecten die studenten van deze klas zelf aanmaakten, met hun uren in :year.', ['year' => $schoolClass->schoolYear->name]) }}</flux:text>
        </div>

        <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @forelse($this->studentProjects as $project)
                <div wire:key="student-project-{{ $project->id }}" class="flex items-center gap-3 px-4 py-3" data-test="student-project-row">
                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $project->color }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium {{ $project->isArchived() ? 'text-zinc-500 line-through' : '' }}">{{ $project->name }}</div>
                        <flux:text size="sm" class="truncate">{{ $project->user->name }}@if($project->client) · {{ $project->client }}@endif</flux:text>
                    </div>
                    <span class="text-sm tabular-nums">{{ Duration::format($project->year_minutes) }}</span>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="chart-bar"
                        :href="route('admin.users.show', ['user' => $project->user, 'periode' => 'year', 'jaar' => $schoolClass->school_year_id])"
                        wire:navigate
                        :aria-label="__('Rapport van :name', ['name' => $project->user->name])"
                    />
                </div>
            @empty
                <div class="p-4"><flux:text>{{ __('De studenten van deze klas hebben nog geen eigen projecten.') }}</flux:text></div>
            @endforelse
        </div>
    </section>

    <flux:modal wire:model.self="showInviteForm" class="md:w-[32rem]">
        <form wire:submit="invite" class="space-y-6" data-test="class-invite-form">
            <div>
                <flux:heading size="lg">{{ __('Studenten uitnodigen in :class', ['class' => $schoolClass->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Eén student per regel: "Naam, e-mailadres" of enkel het e-mailadres.') }}</flux:text>
            </div>

            <flux:textarea wire:model="bulkForm.lines" rows="10" :placeholder="'Voornaam Naam, voornaam.naam@school.be'.PHP_EOL.'andere.student@school.be'" />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="send-class-invites">{{ __('Uitnodigen') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showAddExisting" class="md:w-[32rem]">
        <form wire:submit="addExisting" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Bestaande studenten toevoegen') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Zit een student dit schooljaar al in een andere klas, dan verhuist die naar deze klas.') }}</flux:text>
            </div>

            <flux:input wire:model.live.debounce.300ms="studentSearch" icon="magnifying-glass" autocomplete="off" :placeholder="__('Zoeken')" />

            <div class="max-h-72 space-y-2 overflow-y-auto">
                @forelse($this->availableStudents as $student)
                    <flux:checkbox
                        wire:key="available-{{ $student->id }}"
                        wire:model="selectedStudentIds"
                        :value="(string) $student->id"
                        :label="$student->name"
                        :description="$student->currentSchoolClasses->map(fn ($schoolClass) => $schoolClass->fullName())->implode(', ') ?: $student->email"
                    />
                @empty
                    <flux:text>{{ __('Geen studenten gevonden.') }}</flux:text>
                @endforelse
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Toevoegen') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showTransfer" class="md:w-[30rem]">
        <form wire:submit="transfer" class="space-y-6" data-test="transfer-form">
            <div>
                <flux:heading size="lg">{{ __('Studenten overzetten') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Alle studenten van :class worden toegevoegd aan de gekozen klas. Hun uren en deze klas blijven gewoon bewaard.', ['class' => $schoolClass->fullName()]) }}</flux:text>
            </div>

            @if($this->transferTargets->isEmpty())
                <flux:callout icon="information-circle">
                    <flux:callout.text>{{ __('Maak eerst een klas aan in een ander open schooljaar.') }}</flux:callout.text>
                </flux:callout>
            @else
                <x-listbox wire:model="targetClassId" :label="__('Naar klas')" :options="$this->transferTargets->mapWithKeys(fn ($target) => [$target->id => $target->fullName()])->all()" />
            @endif

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" :disabled="$this->transferTargets->isEmpty()">{{ __('Overzetten') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showProjectForm" class="md:w-[28rem]">
        <form wire:submit="saveProject" class="space-y-6" data-test="class-project-form">
            <flux:heading size="lg">{{ $projectForm->project ? __('Project bewerken') : __('Nieuw project voor :class', ['class' => $schoolClass->name]) }}</flux:heading>

            <flux:input wire:model="projectForm.name" :label="__('Naam')" autofocus />
            <flux:input wire:model="projectForm.client" :label="__('Klant')" :placeholder="__('Optioneel')" />

            <flux:field>
                <flux:label>{{ __('Kleur') }}</flux:label>
                <input wire:model="projectForm.color" type="color" class="h-10 w-20 cursor-pointer rounded-md border border-zinc-200 bg-transparent p-1 dark:border-zinc-700" />
                <flux:error name="projectForm.color" />
            </flux:field>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-class-project">{{ __('Opslaan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
