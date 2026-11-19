<flux:modal name="shortcuts" class="md:w-[26rem]" data-test="shortcuts-modal">
    <div class="space-y-6">
        <flux:heading size="lg">{{ __('Sneltoetsen') }}</flux:heading>

        <dl class="divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            @foreach ([
                'N' => __('Nieuwe registratie'),
                'S' => __('Timer starten of stoppen'),
                '←  →' => __('Vorige of volgende week'),
                'T' => __('Terug naar deze week'),
                '?' => __('Deze lijst tonen'),
            ] as $key => $label)
                <div class="flex items-center justify-between py-2.5">
                    <dt>{{ $label }}</dt>
                    <dd><kbd class="rounded border border-zinc-300 bg-zinc-100 px-2 py-0.5 font-mono text-xs dark:border-zinc-600 dark:bg-zinc-800">{{ $key }}</kbd></dd>
                </div>
            @endforeach
        </dl>

        <flux:text size="sm">{{ __('Sneltoetsen werken niet terwijl je in een invoerveld typt.') }}</flux:text>
    </div>
</flux:modal>
