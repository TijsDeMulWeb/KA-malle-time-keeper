<?php

use App\Support\Duration;
use App\Support\Report;
use App\Support\ReportPeriod;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Rapport')] class extends Component {
    #[Url(as: 'periode')]
    public string $filter = 'course';

    #[Url(as: 'van')]
    public string $from = '';

    #[Url(as: 'tot')]
    public string $until = '';

    public function mount(): void
    {
        if (! in_array($this->filter, ReportPeriod::$filters, true)) {
            $this->filter = 'course';
        }
    }

    public function choose(string $filter): void
    {
        if (! in_array($filter, ReportPeriod::$filters, true)) {
            return;
        }

        $this->filter = $filter;

        if ($filter === 'custom' && $this->from === '') {
            $this->from = ReportPeriod::resolve(auth()->user(), 'week')->from->format('Y-m-d');
            $this->until = ReportPeriod::resolve(auth()->user(), 'week')->until->subDay()->format('Y-m-d');
        }

        unset($this->report);
    }

    public function updatedFrom(): void
    {
        $this->filter = 'custom';

        unset($this->report);
    }

    public function updatedUntil(): void
    {
        $this->filter = 'custom';

        unset($this->report);
    }

    #[Computed]
    public function report(): Report
    {
        $period = ReportPeriod::resolve(auth()->user(), $this->filter, $this->from, $this->until);

        return new Report(auth()->user(), $period);
    }

    /** @return array<string, string> */
    public function exportParameters(): array
    {
        return array_filter([
            'periode' => $this->filter,
            'van' => $this->filter === 'custom' ? $this->from : null,
            'tot' => $this->filter === 'custom' ? $this->until : null,
        ]);
    }
}; ?>

<div class="mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Rapport') }}</flux:heading>
            <flux:subheading data-test="period-label">{{ $this->report->period()->label() }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <flux:button.group>
                <flux:button wire:click="choose('week')" :variant="$filter === 'week' ? 'filled' : 'ghost'" size="sm">{{ __('Deze week') }}</flux:button>
                <flux:button wire:click="choose('last-week')" :variant="$filter === 'last-week' ? 'filled' : 'ghost'" size="sm">{{ __('Vorige week') }}</flux:button>
                <flux:button wire:click="choose('month')" :variant="$filter === 'month' ? 'filled' : 'ghost'" size="sm">{{ __('Deze maand') }}</flux:button>
                <flux:button wire:click="choose('course')" :variant="$filter === 'course' ? 'filled' : 'ghost'" size="sm">{{ __('Hele vak') }}</flux:button>
                <flux:button wire:click="choose('custom')" :variant="$filter === 'custom' ? 'filled' : 'ghost'" size="sm">{{ __('Aangepast') }}</flux:button>
            </flux:button.group>

            <flux:dropdown align="end">
                <flux:button icon="arrow-down-tray" icon:trailing="chevron-down" size="sm">{{ __('Exporteren') }}</flux:button>

                <flux:menu>
                    <flux:menu.item icon="table-cells" :href="route('reports.export.csv', $this->exportParameters())" data-test="export-csv">CSV</flux:menu.item>
                    <flux:menu.item icon="document-text" :href="route('reports.export.pdf', $this->exportParameters())" data-test="export-pdf">PDF</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    @if ($filter === 'custom')
        <div class="flex flex-wrap items-end gap-4">
            <flux:input wire:model.live="from" type="date" :label="__('Van')" class="w-44" />
            <flux:input wire:model.live="until" type="date" :label="__('Tot en met')" class="w-44" />
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="tile-total">
            <flux:text size="sm">{{ __('Totaal in deze periode') }}</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ Duration::format($this->report->totalMinutes()) }}</div>
            <flux:text size="sm">{{ $this->report->count() }} {{ __('registraties') }}</flux:text>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="tile-average">
            <flux:text size="sm">{{ __('Gemiddeld per week') }}</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ Duration::format($this->report->averageMinutesPerWeek()) }}</div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" data-test="tile-course">
            <flux:text size="sm">{{ __('Hele vak') }}</flux:text>
            <div class="mt-1 text-2xl font-semibold">{{ Duration::format($this->report->courseMinutes()) }}</div>
        </div>

    </div>

    <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="weekly-chart">
        <flux:heading>{{ __('Uren per week') }}</flux:heading>

        <div class="mt-6 flex gap-3">
            <div class="relative h-56 w-8 shrink-0">
                @foreach ($this->report->axis()['ticks'] as $tick)
                    <span class="absolute end-0 -translate-y-1/2 text-xs tabular-nums text-zinc-500" style="bottom: {{ $tick / $this->report->axis()['max'] * 100 }}%">{{ $tick }}</span>
                @endforeach
            </div>

            <div class="relative h-56 flex-1">
                @foreach ($this->report->axis()['ticks'] as $tick)
                    <div class="absolute inset-x-0 border-t border-zinc-200 dark:border-zinc-700" style="bottom: {{ $tick / $this->report->axis()['max'] * 100 }}%"></div>
                @endforeach

                <div class="absolute inset-0 flex items-end gap-2 px-1">
                    @foreach ($this->report->weeks() as $week)
                        <div wire:key="week-{{ $week['start']->format('Y-m-d') }}" class="flex h-full min-w-0 flex-1 flex-col-reverse" data-test="week-column" title="{{ $week['label'] }}: {{ Duration::format($week['total']) }}">
                            @foreach ($week['segments'] as $segment)
                                <div
                                    class="w-full first:rounded-b-none last:rounded-t-sm"
                                    style="height: {{ $segment['minutes'] / 60 / $this->report->axis()['max'] * 100 }}%; background-color: {{ $segment['project']->color }}"
                                ></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="ms-11 mt-2 flex gap-2 px-1">
            @foreach ($this->report->weeks() as $week)
                <div class="min-w-0 flex-1 text-center text-xs text-zinc-500">{{ $week['label'] }}</div>
            @endforeach
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-sm">
            @foreach ($this->report->perProject() as $row)
                <span class="flex items-center gap-2">
                    <span class="size-2.5 rounded-full" style="background-color: {{ $row['project']->color }}"></span>
                    {{ $row['project']->name }}
                </span>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="per-project">
            <flux:heading>{{ __('Per project') }}</flux:heading>

            <div class="mt-4 space-y-4">
                @forelse ($this->report->perProject() as $row)
                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span class="flex items-center gap-2">
                                <span class="size-2.5 rounded-full" style="background-color: {{ $row['project']->color }}"></span>
                                {{ $row['project']->name }}
                                @if ($row['project']->client)
                                    <span class="text-xs text-zinc-500">{{ $row['project']->client }}</span>
                                @endif
                            </span>
                            <span><span class="font-medium">{{ Duration::format($row['minutes']) }}</span> <span class="text-xs text-zinc-500">{{ $row['percentage'] }}%</span></span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full" style="width: {{ $row['percentage'] }}%; background-color: {{ $row['project']->color }}"></div>
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('Geen registraties in deze periode.') }}</flux:text>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 p-5 dark:border-zinc-700" data-test="per-category">
            <flux:heading>{{ __('Per categorie') }}</flux:heading>

            <div class="mt-4 space-y-4">
                @forelse ($this->report->perCategory() as $row)
                    <div>
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <span>{{ $row['name'] }}</span>
                            <span><span class="font-medium">{{ Duration::format($row['minutes']) }}</span> <span class="text-xs text-zinc-500">{{ $row['percentage'] }}%</span></span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ $row['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('Geen registraties in deze periode.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</div>
