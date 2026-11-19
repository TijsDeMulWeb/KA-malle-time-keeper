<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ReportPeriod
{
    /** @var array<int, string> */
    public static array $filters = ['week', 'last-week', 'month', 'course', 'custom'];

    public function __construct(
        public string $filter,
        public CarbonImmutable $from,
        public CarbonImmutable $until,
    ) {}

    public static function resolve(User $user, string $filter = 'course', ?string $from = null, ?string $until = null): self
    {
        $thisWeek = LocalTime::startOfWeek();

        if ($filter === 'week') {
            return new self($filter, $thisWeek, $thisWeek->addWeek());
        }

        if ($filter === 'last-week') {
            return new self($filter, $thisWeek->subWeek(), $thisWeek);
        }

        if ($filter === 'month') {
            $start = LocalTime::now()->startOfMonth();

            return new self($filter, $start, $start->addMonth());
        }

        if ($filter === 'custom') {
            return self::custom($thisWeek, $from, $until);
        }

        return self::course($user, $thisWeek);
    }

    public function fromUtc(): CarbonImmutable
    {
        return $this->from->utc();
    }

    public function untilUtc(): CarbonImmutable
    {
        return $this->until->utc();
    }

    /** @return array<int, CarbonImmutable> */
    public function weekStarts(): array
    {
        $weeks = [];
        $week = $this->from->startOfWeek(CarbonInterface::MONDAY);

        while ($week->lessThan($this->until)) {
            $weeks[] = $week;
            $week = $week->addWeek();
        }

        return $weeks;
    }

    public function label(): string
    {
        $last = $this->until->subDay();

        return "{$this->from->translatedFormat('j F Y')} tot en met {$last->translatedFormat('j F Y')}";
    }

    private static function custom(CarbonImmutable $thisWeek, ?string $from, ?string $until): self
    {
        $valid = self::isDate($from) && self::isDate($until);

        if (! $valid) {
            return new self('custom', $thisWeek, $thisWeek->addWeek());
        }

        $start = LocalTime::parse($from);
        $end = LocalTime::parse($until)->addDay();

        if ($end->lessThanOrEqualTo($start)) {
            return new self('custom', $thisWeek, $thisWeek->addWeek());
        }

        return new self('custom', $start, $end);
    }

    private static function course(User $user, CarbonImmutable $thisWeek): self
    {
        $endOfThisWeek = $thisWeek->addWeek();

        $start = $user->period_starts_on
            ? LocalTime::parse($user->period_starts_on->format('Y-m-d'))
            : self::firstEntryDay($user, $thisWeek);

        $end = $endOfThisWeek;

        if ($user->period_ends_on) {
            $periodEnd = LocalTime::parse($user->period_ends_on->format('Y-m-d'))->addDay();
            $end = $periodEnd->lessThan($endOfThisWeek) ? $periodEnd : $endOfThisWeek;
        }

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addWeek();
        }

        return new self('course', $start, $end);
    }

    private static function firstEntryDay(User $user, CarbonImmutable $fallback): CarbonImmutable
    {
        $first = $user->timeEntries()->orderBy('started_at')->value('started_at');

        if (! $first) {
            return $fallback;
        }

        return LocalTime::local(CarbonImmutable::parse($first))->startOfDay();
    }

    private static function isDate(?string $value): bool
    {
        return $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
