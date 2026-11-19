<?php

namespace App\Support;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class Report
{
    private ?Collection $entries = null;

    private ?array $weeks = null;

    public function __construct(
        private User $user,
        private ReportPeriod $period,
    ) {}

    public function period(): ReportPeriod
    {
        return $this->period;
    }

    /** @return Collection<int, TimeEntry> */
    public function entries(): Collection
    {
        return $this->entries ??= $this->user->timeEntries()
            ->startingBetween($this->period->fromUtc(), $this->period->untilUtc())
            ->with(['project', 'category'])
            ->orderBy('started_at')
            ->get();
    }

    public function count(): int
    {
        return $this->entries()->count();
    }

    public function totalMinutes(): int
    {
        return $this->entries()->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
    }

    public function liveMinutes(): int
    {
        return $this->entries()
            ->filter(fn (TimeEntry $entry) => $entry->is_live)
            ->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
    }

    public function livePercentage(): int
    {
        $total = $this->totalMinutes();

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->liveMinutes() / $total * 100);
    }

    public function afterTheFactCount(): int
    {
        return $this->entries()->reject(fn (TimeEntry $entry) => $entry->is_live)->count();
    }

    public function averageMinutesPerWeek(): int
    {
        $weeks = count($this->period->weekStarts());

        return $weeks === 0 ? 0 : intdiv($this->totalMinutes(), $weeks);
    }

    public function courseMinutes(): int
    {
        return $this->user->timeEntries()
            ->when($this->user->period_starts_on, function ($query, $start) {
                $query->where('started_at', '>=', LocalTime::parse($start->format('Y-m-d'))->utc());
            })
            ->when($this->user->period_ends_on, function ($query, $end) {
                $query->where('started_at', '<', LocalTime::parse($end->format('Y-m-d'))->addDay()->utc());
            })
            ->get(['started_at', 'ended_at'])
            ->sum(fn (TimeEntry $entry) => $entry->durationInMinutes());
    }

    /** @return array<int, array{start: CarbonImmutable, label: string, total: int, segments: array<int, array{project: Project, minutes: int}>}> */
    public function weeks(): array
    {
        $byWeek = $this->entries()->groupBy(
            fn (TimeEntry $entry) => $entry->localStartedAt()->startOfWeek()->format('Y-m-d')
        );

        return $this->weeks ??= collect($this->period->weekStarts())
            ->map(function ($start) use ($byWeek) {
                $entries = $byWeek->get($start->format('Y-m-d'), collect());

                $segments = $entries
                    ->groupBy('project_id')
                    ->map(fn (Collection $group) => [
                        'project' => $group->first()->project,
                        'minutes' => $group->sum(fn (TimeEntry $entry) => $entry->durationInMinutes()),
                    ])
                    ->sortBy(fn (array $segment) => $segment['project']->name)
                    ->values()
                    ->all();

                return [
                    'start' => $start,
                    'label' => "wk {$start->isoWeek}",
                    'total' => $entries->sum(fn (TimeEntry $entry) => $entry->durationInMinutes()),
                    'segments' => $segments,
                ];
            })
            ->all();
    }

    /** @return array{max: int, ticks: array<int, int>} */
    public function axis(): array
    {
        $maxHours = (int) ceil(max(array_column($this->weeks(), 'total') ?: [0]) / 60);
        $maxHours = max($maxHours, 1);

        $step = collect([1, 2, 5, 10, 20, 25, 50, 100])
            ->first(fn (int $candidate) => $maxHours / $candidate <= 4, 100);

        $max = (int) ceil($maxHours / $step) * $step;

        return ['max' => $max, 'ticks' => range(0, $max, $step)];
    }

    /** @return array<int, array{project: Project, minutes: int, percentage: int}> */
    public function perProject(): array
    {
        $total = $this->totalMinutes();

        return $this->entries()
            ->groupBy('project_id')
            ->map(fn (Collection $group) => [
                'project' => $group->first()->project,
                'minutes' => $group->sum(fn (TimeEntry $entry) => $entry->durationInMinutes()),
            ])
            ->sortByDesc('minutes')
            ->map(fn (array $row) => $row + ['percentage' => $this->percentage($row['minutes'], $total)])
            ->values()
            ->all();
    }

    /** @return array<int, array{name: string, minutes: int, percentage: int}> */
    public function perCategory(): array
    {
        $total = $this->totalMinutes();

        return $this->entries()
            ->groupBy(fn (TimeEntry $entry) => $entry->category?->name ?? 'Zonder categorie')
            ->map(fn (Collection $group, string $name) => [
                'name' => $name,
                'minutes' => $group->sum(fn (TimeEntry $entry) => $entry->durationInMinutes()),
            ])
            ->sortByDesc('minutes')
            ->map(fn (array $row) => $row + ['percentage' => $this->percentage($row['minutes'], $total)])
            ->values()
            ->all();
    }

    private function percentage(int $minutes, int $total): int
    {
        return $total === 0 ? 0 : (int) round($minutes / $total * 100);
    }
}
