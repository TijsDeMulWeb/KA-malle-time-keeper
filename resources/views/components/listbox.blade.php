@props([
    'label' => null,
    'placeholder' => null,
    'options' => [],
    'size' => null,
])

<flux:field {{ $attributes->whereDoesntStartWith('wire:model')->class('w-full') }}>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    <flux:dropdown class="w-full">
        <button
            type="button"
            x-data="{ options: @js((object) $options), placeholder: @js($placeholder), model: @js($attributes->wire('model')->value()) }"
            @class([
                'flex w-full items-center gap-2 border ps-3 pe-2 text-start shadow-xs',
                'bg-white text-zinc-700 dark:bg-white/10 dark:text-zinc-300',
                'border-zinc-200 border-b-zinc-300/80 dark:border-white/10',
                'h-10 rounded-lg text-base sm:text-sm' => $size !== 'sm',
                'h-8 rounded-md text-sm' => $size === 'sm',
            ])
            data-flux-control
        >
            <span
                class="truncate"
                x-text="options[$wire.get(model) ?? ''] ?? placeholder"
                x-bind:class="options[$wire.get(model) ?? ''] === undefined && 'text-zinc-400'"
            ></span>
            <flux:icon.chevron-up-down variant="mini" class="ms-auto shrink-0 text-zinc-400" />
        </button>

        <flux:menu class="max-h-72 overflow-y-auto">
            <flux:menu.radio.group {{ $attributes->whereStartsWith('wire:model') }}>
                @foreach ($options as $value => $optionLabel)
                    <flux:menu.radio :value="$value">{{ $optionLabel }}</flux:menu.radio>
                @endforeach
            </flux:menu.radio.group>
        </flux:menu>
    </flux:dropdown>

    <flux:error :name="$attributes->wire('model')->value()" />
</flux:field>
