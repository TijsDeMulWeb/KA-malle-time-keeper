<?php

use App\Support\Duration;
use App\Support\LocalTime;
use App\Support\WeekView;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Mijn uren')] class extends Component {
    #[Url(as: 'week')]
    public string $week = '';

    #[Url(as: 'weergave')]
    public string $mode = 'calendar';

    public function previousWeek(): void
    {
        $this->week = $this->weekStart->subWeek()->format('Y-m-d');

        $this->forgetWeek();
    }

    public function nextWeek(): void
    {
        $this->week = $this->weekStart->addWeek()->format('Y-m-d');

        $this->forgetWeek();
    }

    public function currentWeek(): void
    {
        $this->week = '';

        $this->forgetWeek();
    }

    private function forgetWeek(): void
    {
        unset($this->weekStart, $this->weekView);
    }

    #[On('entries-changed')]
    public function refresh(): void
    {
        unset($this->weekView);
    }

    #[Computed]
    public function weekStart()
    {
        $valid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->week) === 1;

        return LocalTime::startOfWeek($valid ? $this->week : null);
    }

    #[Computed]
    public function weekView(): WeekView
    {
        return new WeekView(auth()->user(), $this->weekStart);
    }

    public function weekLabel(): string
    {
        $start = $this->weekStart;
        $end = $start->addDays(6);

        if ($start->month === $end->month) {
            return "{$start->day} – {$end->translatedFormat('j F Y')}";
        }

        return "{$start->translatedFormat('j F')} – {$end->translatedFormat('j F Y')}";
    }

    /** @return array<int, array{label: string, total: int, entries: \Illuminate\Support\Collection}> */
    public function listDays(): array
    {
        return $this->weekView->entriesByDay()
            ->map(fn ($entries, $date) => [
                'label' => LocalTime::parse($date)->translatedFormat('l j F'),
                'total' => $entries->sum(fn ($entry) => $entry->durationInMinutes()),
                'entries' => $entries,
            ])
            ->values()
            ->all();
    }

    public function goalPercentage(): int
    {
        $goal = auth()->user()->weekly_goal_minutes;

        if ($goal === 0) {
            return 0;
        }

        return min(100, (int) round($this->weekView->totalMinutes() / $goal * 100));
    }
}; ?>

<div class="mx-auto w-full max-w-7xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-6">
        <div>
            <flux:text size="sm" class="uppercase tracking-wide">{{ __('Week') }} {{ $this->weekStart->isoWeek }}</flux:text>
            <div class="flex items-center gap-3">
                <flux:heading size="xl" level="1">{{ $this->weekLabel() }}</flux:heading>
                <flux:button variant="ghost" size="sm" icon="chevron-left" wire:click="previousWeek" :aria-label="__('Vorige week')" data-test="previous-week" />
                <flux:button variant="ghost" size="sm" icon="chevron-right" wire:click="nextWeek" :aria-label="__('Volgende week')" data-test="next-week" />
                @if ($week !== '')
                    <flux:button variant="subtle" size="sm" wire:click="currentWeek">{{ __('Vandaag') }}</flux:button>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-6">
            <div class="w-64" data-test="week-goal">
                <div class="flex items-baseline justify-between text-sm">
                    <span class="font-medium">{{ Duration::format($this->weekView->totalMinutes()) }}</span>
                    <span class="text-zinc-500">{{ __('van') }} {{ Duration::format(auth()->user()->weekly_goal_minutes) }}</span>
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div class="h-full rounded-full bg-teal-500" style="width: {{ $this->goalPercentage() }}%"></div>
                </div>
            </div>

            <div class="text-sm" data-test="live-percentage">
                <div class="font-semibold">{{ $this->weekView->livePercentage() }}%</div>
                <div class="text-zinc-500">{{ __('live getimed') }}</div>
            </div>

            <flux:radio.group wire:model.live="mode" variant="segmented">
                <flux:radio value="calendar" icon="calendar-days" :label="__('Kalender')" />
                <flux:radio value="list" icon="list-bullet" :label="__('Lijst')" />
            </flux:radio.group>
        </div>
    </div>

    @if ($mode === 'list')
        <div class="space-y-6" data-test="entry-list">
            @forelse ($this->listDays() as $day)
                <section wire:key="list-day-{{ $loop->index }}" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <header class="flex items-center justify-between bg-zinc-50 px-4 py-2.5 dark:bg-zinc-900">
                        <span class="font-medium first-letter:uppercase">{{ $day['label'] }}</span>
                        <span class="text-sm text-zinc-500">{{ Duration::format($day['total']) }}</span>
                    </header>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($day['entries'] as $entry)
                            <li wire:key="list-entry-{{ $entry->id }}">
                                <button
                                    type="button"
                                    wire:click="$dispatch('open-entry', { entry: {{ $entry->id }} })"
                                    class="flex w-full items-center gap-4 px-4 py-3 text-start hover:bg-zinc-50 dark:hover:bg-zinc-900"
                                >
                                    <span class="w-28 shrink-0 text-sm tabular-nums text-zinc-500">
                                        {{ $entry->localStartedAt()->format('H:i') }} – {{ $entry->localEndedAt()?->format('H:i') ?? __('nu') }}
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium">{{ $entry->description !== '' ? $entry->description : __('Zonder omschrijving') }}</span>
                                        <span class="flex items-center gap-2 text-sm text-zinc-500">
                                            <span class="size-2 rounded-full" style="background-color: {{ $entry->project->color }}"></span>
                                            {{ $entry->project->name }}
                                            @if ($entry->category)
                                                <span>· {{ $entry->category->name }}</span>
                                            @endif
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-sm font-medium tabular-nums">{{ Duration::format($entry->durationInMinutes()) }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @empty
                <flux:callout icon="clock">
                    <flux:callout.heading>{{ __('Geen registraties in deze week') }}</flux:callout.heading>
                </flux:callout>
            @endforelse
        </div>
    @else
        <div wire:poll.60s class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700" data-test="calendar">
            <div class="grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))] border-b border-zinc-200 dark:border-zinc-700">
                <div></div>
                @foreach ($this->weekView->days() as $day)
                    <div
                        wire:key="day-head-{{ $loop->index }}"
                        @class([
                            'border-s border-zinc-200 px-3 py-2 dark:border-zinc-700',
                            'bg-sky-50 dark:bg-sky-950/30' => $day['isToday'],
                        ])
                    >
                        <div class="text-sm">
                            <span class="uppercase text-zinc-500">{{ $day['date']->translatedFormat('D') }}</span>
                            <span @class(['font-semibold', 'text-sky-600 dark:text-sky-400' => $day['isToday']])>{{ $day['date']->day }}</span>
                        </div>
                        <div class="text-xs text-zinc-500" data-test="day-total">{{ Duration::format($day['totalMinutes']) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="overflow-y-auto" style="height: 576px">
                <div
                    class="relative grid grid-cols-[3.5rem_repeat(7,minmax(0,1fr))]"
                    style="height: {{ $this->weekView->gridHeight() }}px"
                >
                    <div class="relative">
                        <div class="scroll-initial-target absolute h-px w-px" style="top: {{ 8 * $this->weekView->pixelsPerHour - 10 }}px"></div>

                        @foreach (range(0, 23) as $hour)
                            <div
                                class="absolute end-2 -translate-y-1/2 text-xs tabular-nums text-zinc-500"
                                style="top: {{ $hour * $this->weekView->pixelsPerHour }}px"
                            >
                                @if ($hour > 0){{ sprintf('%02d:00', $hour) }}@endif
                            </div>
                        @endforeach
                    </div>

                    @foreach ($this->weekView->days() as $day)
                        <div
                            wire:key="day-column-{{ $loop->index }}"
                            @class([
                                'relative border-s border-zinc-200 dark:border-zinc-700',
                                'bg-sky-50/50 dark:bg-sky-950/20' => $day['isToday'],
                            ])
                            data-test="day-column"
                        >
                            @foreach (range(0, 23) as $hour)
                                <button
                                    type="button"
                                    wire:click="$dispatch('open-entry', { date: '{{ $day['date']->format('Y-m-d') }}', start: '{{ sprintf('%02d:00', $hour) }}', end: '{{ sprintf('%02d:00', $hour + 1) }}' })"
                                    class="absolute inset-x-0 block cursor-pointer border-t border-zinc-200 hover:bg-zinc-100/60 dark:border-zinc-800 dark:hover:bg-zinc-800/40"
                                    style="top: {{ $hour * $this->weekView->pixelsPerHour }}px; height: {{ $this->weekView->pixelsPerHour }}px"
                                    aria-label="{{ sprintf('%02d:00', $hour) }}"
                                ></button>
                            @endforeach

                            @foreach ($day['blocks'] as $block)
                                <button
                                    type="button"
                                    wire:key="block-{{ $block['entry']->id }}-{{ $loop->parent->index }}"
                                    wire:click="$dispatch('open-entry', { entry: {{ $block['entry']->id }} })"
                                    class="absolute inset-x-0.5 z-10 overflow-hidden rounded-md px-2 py-1 text-start text-xs leading-tight"
                                    style="top: {{ $block['top'] }}px; height: {{ $block['height'] }}px; background-color: color-mix(in srgb, {{ $block['entry']->project->color }} 25%, transparent); border-inline-start: 3px solid {{ $block['entry']->project->color }}"
                                    data-test="entry-block"
                                >
                                    <span class="block truncate font-semibold">{{ $block['entry']->description !== '' ? $block['entry']->description : __('Zonder omschrijving') }}</span>
                                    <span class="block truncate opacity-75">{{ $block['entry']->project->name }}</span>
                                    @if ($block['height'] >= 52)
                                        <span class="block truncate opacity-75">
                                            {{ $block['startLabel'] }} – {{ $block['endLabel'] ?? __('nu') }} · {{ Duration::format($block['minutes']) }}
                                        </span>
                                    @endif
                                </button>
                            @endforeach

                            @if ($day['nowTop'] !== null)
                                <div class="pointer-events-none absolute inset-x-0 z-20 border-t-2 border-red-500" style="top: {{ $day['nowTop'] }}px" data-test="now-line">
                                    <span class="absolute -start-1 -top-1.5 size-2.5 rounded-full bg-red-500"></span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <flux:text size="sm">{{ __('Klik op een leeg uur om een registratie toe te voegen. Klik op een blok om te bewerken.') }}</flux:text>
    @endif
</div>
