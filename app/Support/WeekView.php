<?php

namespace App\Support;

use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WeekView
{
    public int $pixelsPerHour = 56;

    public int $minimumBlockHeight = 18;

    private ?Collection $entries = null;

    public function __construct(
        private User $user,
        private CarbonImmutable $weekStart,
    ) {}

    public function weekStart(): CarbonImmutable
    {
        return $this->weekStart;
    }

    public function weekEnd(): CarbonImmutable
    {
        return $this->weekStart->addWeek();
    }

    public function gridHeight(): float
    {
        return 24 * $this->pixelsPerHour;
    }

    public function entries(): Collection
    {
        return $this->entries ??= $this->user->timeEntries()
            ->overlapping($this->weekStart->utc(), $this->weekEnd()->utc())
            ->with(['project', 'category'])
            ->orderBy('started_at')
            ->get();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function days(): Collection
    {
        $today = LocalTime::now()->startOfDay();

        return collect(range(0, 6))->map(function (int $offset) use ($today) {
            $day = $this->weekStart->addDays($offset);
            $blocks = $this->blocksFor($day);

            return [
                'date' => $day,
                'isToday' => $day->equalTo($today),
                'totalMinutes' => $blocks->sum('minutes'),
                'blocks' => $blocks,
                'nowTop' => $day->equalTo($today) ? $this->top($this->wallMinutes(LocalTime::now())) : null,
            ];
        });
    }

    public function totalMinutes(): int
    {
        return $this->entries()->sum(fn (TimeEntry $entry) => $this->minutesInWeek($entry));
    }

    public function liveMinutes(): int
    {
        return $this->entries()
            ->filter(fn (TimeEntry $entry) => $entry->is_live)
            ->sum(fn (TimeEntry $entry) => $this->minutesInWeek($entry));
    }

    public function livePercentage(): int
    {
        $total = $this->totalMinutes();

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->liveMinutes() / $total * 100);
    }

    /** @return Collection<string, Collection<int, TimeEntry>> */
    public function entriesByDay(): Collection
    {
        return $this->entries()
            ->sortByDesc('started_at')
            ->groupBy(fn (TimeEntry $entry) => $entry->localStartedAt()->format('Y-m-d'));
    }

    private function minutesInWeek(TimeEntry $entry): int
    {
        return $entry->minutesWithin($this->weekStart->utc(), $this->weekEnd()->utc());
    }

    /** @return Collection<int, array<string, mixed>> */
    private function blocksFor(CarbonImmutable $day): Collection
    {
        $dayStart = $day->utc();
        $dayEnd = $day->addDay()->utc();

        return $this->entries()
            ->filter(fn (TimeEntry $entry) => $entry->minutesWithin($dayStart, $dayEnd) > 0 || $this->startsInside($entry, $dayStart, $dayEnd))
            ->map(function (TimeEntry $entry) use ($dayStart, $dayEnd) {
                $start = $entry->started_at->greaterThan($dayStart) ? $entry->started_at : $dayStart;
                $end = $entry->ended_at ?? now();
                $end = $end->lessThan($dayEnd) ? $end : $dayEnd;

                $startMinutes = $start->equalTo($dayStart) ? 0 : $this->wallMinutes(LocalTime::local($start));
                $endMinutes = $end->equalTo($dayEnd) ? 1440 : $this->wallMinutes(LocalTime::local($end));

                return [
                    'entry' => $entry,
                    'top' => $this->top($startMinutes),
                    'height' => max($this->top($endMinutes - $startMinutes), $this->minimumBlockHeight),
                    'minutes' => $entry->minutesWithin($dayStart, $dayEnd),
                    'startLabel' => LocalTime::local($start)->format('H:i'),
                    'endLabel' => $entry->ended_at ? LocalTime::local($end)->format('H:i') : null,
                    'continuesFromPrevious' => $entry->started_at->lessThan($dayStart),
                    'continuesToNext' => $entry->ended_at !== null && $entry->ended_at->greaterThan($dayEnd),
                    'startMinutes' => $startMinutes,
                    'endMinutes' => $endMinutes,
                    'draggable' => $entry->ended_at !== null
                        && $entry->started_at->greaterThanOrEqualTo($dayStart)
                        && $entry->ended_at->lessThanOrEqualTo($dayEnd),
                ];
            })
            ->values();
    }

    private function startsInside(TimeEntry $entry, CarbonImmutable $dayStart, CarbonImmutable $dayEnd): bool
    {
        return $entry->started_at->greaterThanOrEqualTo($dayStart) && $entry->started_at->lessThan($dayEnd);
    }

    private function wallMinutes(CarbonImmutable $local): int
    {
        return $local->hour * 60 + $local->minute;
    }

    private function top(int $minutes): float
    {
        return round($minutes * $this->pixelsPerHour / 60, 2);
    }
}
