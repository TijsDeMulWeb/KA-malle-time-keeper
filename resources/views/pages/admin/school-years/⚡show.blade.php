<?php

use App\Enums\Role;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Schooljaar')] class extends Component {
    #[Locked]
    public SchoolYear $schoolYear;

    public string $name = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public bool $showCloseModal = false;

    public function mount(SchoolYear $schoolYear): void
    {
        $this->authorize('view', $schoolYear);

        $this->schoolYear = $schoolYear;
    }

    public function store(): void
    {
        $this->authorize('create', SchoolClass::class);

        if ($this->schoolYear->isClosed()) {
            return;
        }

        $this->name = trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique(SchoolClass::class)->where('school_year_id', $this->schoolYear->id)],
        ], attributes: ['name' => 'naam']);

        $this->schoolYear->schoolClasses()->create(['name' => $this->name]);

        $this->reset('name');
    }

    public function edit(int $schoolClassId): void
    {
        $schoolClass = $this->schoolYear->schoolClasses()->findOrFail($schoolClassId);

        $this->authorize('update', $schoolClass);

        $this->resetErrorBag();
        $this->editingId = $schoolClass->id;
        $this->editingName = $schoolClass->name;
    }

    public function update(): void
    {
        $schoolClass = $this->schoolYear->schoolClasses()->findOrFail($this->editingId);

        $this->authorize('update', $schoolClass);

        $this->editingName = trim($this->editingName);

        $this->validate([
            'editingName' => [
                'required',
                'string',
                'max:50',
                Rule::unique(SchoolClass::class, 'name')->where('school_year_id', $this->schoolYear->id)->ignore($schoolClass->id),
            ],
        ], attributes: ['editingName' => 'naam']);

        $schoolClass->update(['name' => $this->editingName]);

        $this->reset('editingId', 'editingName');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingName');
    }

    public function delete(int $schoolClassId): void
    {
        $schoolClass = $this->schoolYear->schoolClasses()->findOrFail($schoolClassId);

        $this->authorize('delete', $schoolClass);

        $schoolClass->delete();

        Flux::toast(text: __('Klas verwijderd.'));
    }

    public function close(): void
    {
        $this->authorize('update', $this->schoolYear);

        $this->schoolYear->update(['closed_at' => now()]);

        $this->showCloseModal = false;

        Flux::toast(variant: 'success', text: __('Schooljaar :name is afgesloten.', ['name' => $this->schoolYear->name]));
    }

    public function reopen(): void
    {
        $this->authorize('update', $this->schoolYear);

        $this->schoolYear->update(['closed_at' => null]);

        Flux::toast(variant: 'success', text: __('Schooljaar :name is heropend.', ['name' => $this->schoolYear->name]));
    }

    #[Computed]
    public function schoolClasses(): Collection
    {
        return $this->schoolYear->schoolClasses()
            ->withCount(['students', 'projects'])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function studentsLosingAccess(): int
    {
        return User::role(Role::Student->value)
            ->whereHas('schoolClasses', fn ($query) => $query->where('school_year_id', $this->schoolYear->id))
            ->whereDoesntHave('schoolClasses', function ($query) {
                $query->where('school_year_id', '!=', $this->schoolYear->id)
                    ->inOpenYear();
            })
            ->count();
    }
}; ?>

<div class="mx-auto w-full max-w-4xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:link :href="route('admin.school-years.index')" variant="subtle" class="text-sm" wire:navigate>&larr; {{ __('Schooljaren') }}</flux:link>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $schoolYear->name }}</flux:heading>
                @if($schoolYear->isClosed())
                    <flux:badge size="sm" icon="lock-closed">{{ __('Afgesloten') }}</flux:badge>
                @endif
            </div>
            <flux:subheading>{{ $schoolYear->starts_on->translatedFormat('j F Y') }} tot en met {{ $schoolYear->ends_on->translatedFormat('j F Y') }}</flux:subheading>
        </div>

        @if($schoolYear->isClosed())
            <flux:button icon="lock-open" wire:click="reopen" wire:confirm="{{ __('Dit schooljaar heropenen? Uren kunnen dan weer gewijzigd worden.') }}" data-test="reopen-school-year">{{ __('Heropenen') }}</flux:button>
        @else
            <flux:button icon="lock-closed" wire:click="$set('showCloseModal', true)" data-test="close-school-year">{{ __('Schooljaar afsluiten') }}</flux:button>
        @endif
    </div>

    @if($schoolYear->isClosed())
        <flux:callout icon="lock-closed" variant="secondary">
            <flux:callout.text>{{ __('Dit schooljaar is afgesloten. Alle uren, klassen en projecten zijn bewaard en alleen-lezen.') }}</flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="store" class="flex items-start gap-3" data-test="school-class-form">
            <flux:input wire:model="name" :placeholder="__('Nieuwe klas, bijvoorbeeld 6A')" class="flex-1" />
            <flux:button type="submit" variant="primary" icon="plus" data-test="save-school-class">{{ __('Klas toevoegen') }}</flux:button>
        </form>
    @endif

    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse($this->schoolClasses as $schoolClass)
            <div wire:key="school-class-{{ $schoolClass->id }}" class="flex items-center gap-3 px-4 py-3" data-test="school-class-row">
                @if($editingId === $schoolClass->id)
                    <form wire:submit="update" class="flex flex-1 items-start gap-2">
                        <flux:input wire:model="editingName" size="sm" class="flex-1" autofocus />
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Opslaan') }}</flux:button>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit">{{ __('Annuleren') }}</flux:button>
                    </form>
                @else
                    <a href="{{ route('admin.school-classes.show', $schoolClass) }}" wire:navigate class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $schoolClass->name }}</div>
                        <flux:text size="sm">
                            {{ trans_choice(':count student|:count studenten', $schoolClass->students_count) }}
                            · {{ trans_choice(':count project|:count projecten', $schoolClass->projects_count) }}
                        </flux:text>
                    </a>

                    @unless($schoolYear->isClosed())
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $schoolClass->id }})" :aria-label="__('Hernoemen')" />
                        @if($schoolClass->students_count === 0 && $schoolClass->projects_count === 0)
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $schoolClass->id }})" wire:confirm="{{ __('Deze klas verwijderen?') }}" :aria-label="__('Verwijderen')" />
                        @endif
                    @endunless

                    <flux:icon.chevron-right variant="micro" class="text-zinc-400" />
                @endif
            </div>
        @empty
            <div class="p-4"><flux:text>{{ __('Nog geen klassen in dit schooljaar.') }}</flux:text></div>
        @endforelse
    </div>

    <flux:modal wire:model.self="showCloseModal" class="md:w-[30rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Schooljaar :name afsluiten?', ['name' => $schoolYear->name]) }}</flux:heading>
                <flux:text class="mt-2">{{ __('Alle uren blijven bewaard, maar niemand kan ze nog wijzigen. Rapporten en exports blijven beschikbaar.') }}</flux:text>
                @if($this->studentsLosingAccess > 0)
                    <flux:callout icon="exclamation-triangle" color="amber" class="mt-4" data-test="students-losing-access">
                        <flux:callout.text>
                            {{ trans_choice(':count student zit niet in een klas van een ander open schooljaar en kan daarna niet meer inloggen.|:count studenten zitten niet in een klas van een ander open schooljaar en kunnen daarna niet meer inloggen.', $this->studentsLosingAccess) }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            <div class="flex justify-end gap-3">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Annuleren') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="close" data-test="confirm-close-school-year">{{ __('Afsluiten') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
