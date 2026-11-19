<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categorieën')] class extends Component {
    public string $name = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public function store(): void
    {
        $this->authorize('create', Category::class);

        $this->name = trim($this->name);

        $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Category::class)],
        ], attributes: ['name' => 'naam']);

        Category::create(['name' => $this->name]);

        $this->reset('name');

        Flux::toast(variant: 'success', text: __('Categorie toegevoegd.'));
    }

    public function edit(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        $this->authorize('update', $category);

        $this->resetErrorBag();
        $this->editingId = $category->id;
        $this->editingName = $category->name;
    }

    public function update(): void
    {
        $category = Category::findOrFail($this->editingId);

        $this->authorize('update', $category);

        $this->editingName = trim($this->editingName);

        $this->validate([
            'editingName' => ['required', 'string', 'max:255', Rule::unique(Category::class, 'name')->ignore($category->id)],
        ], attributes: ['editingName' => 'naam']);

        $category->update(['name' => $this->editingName]);

        $this->reset('editingId', 'editingName');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editingName');
    }

    public function delete(int $categoryId): void
    {
        $category = Category::findOrFail($categoryId);

        $this->authorize('delete', $category);

        $category->delete();

        Flux::toast(text: __('Categorie verwijderd.'));
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::withCount('timeEntries')->orderBy('name')->get();
    }
}; ?>

<div class="mx-auto w-full max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Categorieën') }}</flux:heading>
        <flux:subheading>{{ __('De categorieën waaruit iedereen kiest bij het registreren van uren.') }}</flux:subheading>
    </div>

    <form wire:submit="store" class="flex items-start gap-3" data-test="category-form">
        <flux:input wire:model="name" :placeholder="__('Nieuwe categorie')" class="flex-1" />
        <flux:button type="submit" variant="primary" icon="plus" data-test="save-category">{{ __('Toevoegen') }}</flux:button>
    </form>

    <div class="divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
        @forelse($this->categories as $category)
            <div wire:key="category-{{ $category->id }}" class="flex items-center gap-3 px-4 py-3" data-test="category-row">
                @if($editingId === $category->id)
                    <form wire:submit="update" class="flex flex-1 items-start gap-2">
                        <flux:input wire:model="editingName" size="sm" class="flex-1" autofocus />
                        <flux:button type="submit" size="sm" variant="primary">{{ __('Opslaan') }}</flux:button>
                        <flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit">{{ __('Annuleren') }}</flux:button>
                    </form>
                @else
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ $category->name }}</div>
                        <flux:text size="sm">{{ trans_choice(':count registratie|:count registraties', $category->time_entries_count) }}</flux:text>
                    </div>

                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="edit({{ $category->id }})" :aria-label="__('Bewerken')" />
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="delete({{ $category->id }})"
                        wire:confirm="{{ __('Deze categorie verwijderen? Bestaande registraties blijven bestaan, zonder categorie.') }}"
                        :aria-label="__('Verwijderen')"
                    />
                @endif
            </div>
        @empty
            <div class="p-4"><flux:text>{{ __('Nog geen categorieën.') }}</flux:text></div>
        @endforelse
    </div>
</div>
