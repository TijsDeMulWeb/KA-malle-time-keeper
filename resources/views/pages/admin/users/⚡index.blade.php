<?php

use App\Actions\InviteStudents;
use App\Actions\SendInvitation;
use App\Enums\Role;
use App\Livewire\Forms\BulkInviteForm;
use App\Livewire\Forms\UserForm;
use App\Models\SchoolClass;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Duration;
use App\Support\LocalTime;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Gebruikers')] class extends Component {
    public UserForm $form;

    public BulkInviteForm $bulkForm;

    #[Url(as: 'zoek')]
    public string $search = '';

    #[Url(as: 'rol')]
    public string $role = '';

    public bool $showForm = false;

    public bool $showBulkForm = false;

    public ?int $deletingUserId = null;

    public bool $showDeleteModal = false;

    public function create(): void
    {
        $this->authorize('create', User::class);

        $this->form->reset();
        $this->form->resetErrorBag();
        $this->form->schoolClassId = (string) ($this->schoolClassOptions->first()?->id ?? '');
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::with('roles')->findOrFail($userId);

        $this->authorize('update', $user);

        $this->form->resetErrorBag();
        $this->form->fillFrom($user);
        $this->showForm = true;
    }

    public function save(): void
    {
        $isNew = $this->form->user === null;

        $this->authorize($isNew ? 'create' : 'update', $this->form->user ?? User::class);

        $user = $this->form->save();

        $this->showForm = false;
        $this->form->reset();

        if (! $isNew) {
            Flux::toast(variant: 'success', text: __('Gebruiker bijgewerkt.'));

            return;
        }

        $this->invite($user);
    }

    public function createMany(): void
    {
        $this->authorize('create', User::class);

        $this->bulkForm->resetErrorBag();
        $this->bulkForm->reset();
        $this->bulkForm->schoolClassId = (string) ($this->schoolClassOptions->first()?->id ?? '');
        $this->showBulkForm = true;
    }

    public function saveMany(): void
    {
        $this->authorize('create', User::class);

        $students = $this->bulkForm->students();

        if ($students === null) {
            return;
        }

        $failed = app(InviteStudents::class)->execute($students, $this->bulkForm->schoolClass());

        $this->showBulkForm = false;
        $this->bulkForm->reset();

        if ($failed->isNotEmpty()) {
            Flux::toast(variant: 'danger', text: __('Uitnodiging niet verstuurd naar: :emails', ['emails' => $failed->implode(', ')]));

            return;
        }

        Flux::toast(variant: 'success', text: __(':count uitnodigingen verstuurd.', ['count' => $students->count()]));
    }

    public function resendInvitation(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->authorize('update', $user);

        if ($user->hasAcceptedInvitation()) {
            return;
        }

        $this->invite($user);
    }

    public function confirmDelete(int $userId): void
    {
        $this->authorize('delete', User::findOrFail($userId));

        $this->deletingUserId = $userId;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $user = User::findOrFail($this->deletingUserId);

        $this->authorize('delete', $user);

        $user->delete();

        $this->deletingUserId = null;
        $this->showDeleteModal = false;

        Flux::toast(text: __('Gebruiker verwijderd.'));
    }

    #[Computed]
    public function deletingUser(): ?User
    {
        return $this->deletingUserId ? User::find($this->deletingUserId) : null;
    }

    #[Computed]
    public function users(): Collection
    {
        $users = User::with(['roles', 'currentSchoolClasses.schoolYear'])
            ->when($this->role !== '', fn ($query) => $query->role($this->role))
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->get();

        $weekStart = LocalTime::startOfWeek()->utc();
        $weekEnd = $weekStart->addWeek();

        $entries = TimeEntry::whereIn('user_id', $users->modelKeys())
            ->get(['user_id', 'started_at', 'ended_at'])
            ->groupBy('user_id');

        return $users->each(function (User $user) use ($entries, $weekStart, $weekEnd) {
            $userEntries = $entries->get($user->id, collect());

            $user->total_minutes = $userEntries->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
            $user->week_minutes = $userEntries->sum(fn (TimeEntry $entry) => $entry->minutesWithin($weekStart, $weekEnd));
        });
    }

    #[Computed]
    public function schoolClassOptions(): Collection
    {
        return SchoolClass::inOpenYear()
            ->with('schoolYear')
            ->get()
            ->sortBy(fn (SchoolClass $schoolClass) => [$schoolClass->schoolYear->starts_on, $schoolClass->name])
            ->values();
    }

    /** @return array<int, Role> */
    public function roles(): array
    {
        return Role::cases();
    }

    private function invite(User $user): void
    {
        if (! app(SendInvitation::class)->execute($user)) {
            Flux::toast(variant: 'danger', text: __('De uitnodiging kon niet verstuurd worden. Probeer het later opnieuw.'));

            return;
        }

        Flux::toast(variant: 'success', text: __('Uitnodiging verstuurd naar :email.', ['email' => $user->email]));
    }
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Gebruikers') }}</flux:heading>
            <flux:subheading>{{ __('Nodig studenten uit en bekijk hun uren.') }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Zoeken')" class="w-44" />

            <flux:select wire:model.live="role" class="w-36">
                <flux:select.option value="">{{ __('Alle rollen') }}</flux:select.option>
                @foreach($this->roles() as $roleOption)
                    <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button icon="users" wire:click="createMany" data-test="bulk-invite">
                {{ __('Meerdere uitnodigen') }}
            </flux:button>

            <flux:button variant="primary" icon="plus" wire:click="create" data-test="new-user">
                {{ __('Gebruiker uitnodigen') }}
            </flux:button>
        </div>
    </div>

    @if($this->users->isEmpty())
        <flux:callout icon="users">
            <flux:callout.heading>{{ __('Geen gebruikers gevonden') }}</flux:callout.heading>
        </flux:callout>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Naam') }}</flux:table.column>
                <flux:table.column class="max-sm:hidden">{{ __('Rol') }}</flux:table.column>
                <flux:table.column class="max-sm:hidden">{{ __('Klas') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column align="end" class="max-md:hidden">{{ __('Deze week') }}</flux:table.column>
                <flux:table.column align="end" class="max-md:hidden">{{ __('Totaal') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach($this->users as $user)
                    <flux:table.row :key="'user-'.$user->id" data-test="user-row">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                <div class="min-w-0">
                                    <div class="truncate font-medium text-zinc-800 dark:text-white">{{ $user->name }}</div>
                                    <div class="truncate text-xs text-zinc-500">{{ $user->email }}</div>
                                </div>
                            </div>
                        </flux:table.cell>

                        <flux:table.cell class="max-sm:hidden">{{ Role::tryFrom($user->getRoleNames()->first() ?? '')?->label() ?? '-' }}</flux:table.cell>
                        <flux:table.cell class="max-sm:hidden" data-test="user-class">
                            {{ $user->currentSchoolClasses->map(fn ($schoolClass) => $schoolClass->fullName())->implode(', ') ?: '-' }}
                        </flux:table.cell>

                        <flux:table.cell>
                            @if($user->hasAcceptedInvitation())
                                <flux:badge size="sm" color="green" inset="top bottom">{{ __('Actief') }}</flux:badge>
                            @elseif($user->invitationHasExpired())
                                <flux:badge size="sm" color="red" inset="top bottom">{{ __('Uitnodiging verlopen') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber" inset="top bottom" :title="$user->invited_at?->format('d/m/Y H:i')">{{ __('Uitgenodigd') }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell align="end" class="tabular-nums max-md:hidden">{{ Duration::format($user->week_minutes) }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums max-md:hidden">{{ Duration::format($user->total_minutes) }}</flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" />

                                <flux:menu>
                                    <flux:menu.item icon="chart-bar" :href="route('admin.users.show', $user)" wire:navigate>{{ __('Rapport bekijken') }}</flux:menu.item>
                                        <x-impersonate-menu-item :user="$user" />
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">{{ __('Bewerken') }}</flux:menu.item>
                                    @unless($user->hasAcceptedInvitation())
                                        <flux:menu.item icon="envelope" wire:click="resendInvitation({{ $user->id }})" data-test="resend-invitation">{{ __('Uitnodiging opnieuw sturen') }}</flux:menu.item>
                                    @endunless
                                    @unless($user->is(auth()->user()))
                                        <flux:menu.separator />
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $user->id }})">{{ __('Verwijderen') }}</flux:menu.item>
                                    @endunless
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal wire:model.self="showForm" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-6" data-test="user-form">
            <div>
                <flux:heading size="lg">{{ $form->user ? __('Gebruiker bewerken') : __('Gebruiker uitnodigen') }}</flux:heading>
                @unless($form->user)
                    <flux:text class="mt-2">{{ __('We sturen een mail met een link om een wachtwoord in te stellen.') }}</flux:text>
                @endunless
            </div>

            <flux:input wire:model="form.name" :label="__('Naam')" autofocus />
            <flux:input wire:model="form.email" type="email" :label="__('E-mailadres')" />

            <flux:select wire:model="form.schoolClassId" :label="__('Klas')">
                <flux:select.option value="">{{ __('Geen klas') }}</flux:select.option>
                @foreach($this->schoolClassOptions as $schoolClass)
                    <flux:select.option :value="$schoolClass->id">{{ $schoolClass->fullName() }}</flux:select.option>
                @endforeach
            </flux:select>

            @if(! $form->user?->is(auth()->user()))
                <flux:radio.group wire:model="form.role" :label="__('Rol')" variant="segmented">
                    @foreach($this->roles() as $roleOption)
                        <flux:radio :value="$roleOption->value" :label="$roleOption->label()" />
                    @endforeach
                </flux:radio.group>
            @endif

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-user">
                    {{ $form->user ? __('Opslaan') : __('Uitnodigen') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showBulkForm" class="md:w-[32rem]">
        <form wire:submit="saveMany" class="space-y-6" data-test="bulk-invite-form">
            <div>
                <flux:heading size="lg">{{ __('Studenten uitnodigen') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Eén student per regel: "Naam, e-mailadres" of enkel het e-mailadres. Iedereen krijgt een uitnodiging als student.') }}</flux:text>
                @if($this->schoolClassOptions->isEmpty())
                    <flux:text class="mt-2 text-amber-600">{{ __('Er is nog geen klas in een open schooljaar. Studenten zonder klas kunnen niet inloggen.') }}</flux:text>
                @endif
            </div>

            <flux:select wire:model="bulkForm.schoolClassId" :label="__('Klas')">
                <flux:select.option value="">{{ __('Geen klas') }}</flux:select.option>
                @foreach($this->schoolClassOptions as $schoolClass)
                    <flux:select.option :value="$schoolClass->id">{{ $schoolClass->fullName() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="bulkForm.lines" rows="10" :placeholder="'Voornaam Naam, voornaam.naam@school.be'.PHP_EOL.'andere.student@school.be'" />

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" data-test="save-bulk-invite">{{ __('Uitnodigen') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showDeleteModal" class="md:w-[28rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Gebruiker verwijderen?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Het account van :name wordt verwijderd, samen met alle uren en eigen projecten.', ['name' => $this->deletingUser?->name]) }}</flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-user">{{ __('Verwijderen') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
