<?php

namespace App\Support;

use App\Models\SchoolYear;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class ReportPeriod
{
    /** @var array<int, string> */
    public static array $filters = ['week', 'last-week', 'month', 'year', 'custom'];

    public function __construct(
        public string $filter,
        public CarbonImmutable $from,
        public CarbonImmutable $until,
    ) {}

    public static function resolve(
        User $user,
        string $filter = 'year',
        ?string $from = null,
        ?string $until = null,
        ?SchoolYear $schoolYear = null,
    ): self {
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

        $schoolYear ??= self::defaultSchoolYear($user);

        if (! $schoolYear) {
            return self::sinceFirstEntry($user, $thisWeek);
        }

        return self::schoolYear($schoolYear, $thisWeek);
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

    private static function schoolYear(SchoolYear $schoolYear, CarbonImmutable $thisWeek): self
    {
        $endOfThisWeek = $thisWeek->addWeek();
        $start = $schoolYear->startsAt();
        $end = $schoolYear->endsAt()->lessThan($endOfThisWeek) ? $schoolYear->endsAt() : $endOfThisWeek;

        if ($end->lessThanOrEqualTo($start)) {
            $end = $start->addWeek();
        }

        return new self('year', $start, $end);
    }

    private static function defaultSchoolYear(User $user): ?SchoolYear
    {
        $schoolYears = SchoolYear::whereHas('schoolClasses.students', fn ($query) => $query->whereKey($user->id))
            ->orderByDesc('starts_on')
            ->get();

        $today = LocalTime::now()->format('Y-m-d');

        return $schoolYears->first(fn (SchoolYear $schoolYear) => $schoolYear->starts_on->format('Y-m-d') <= $today)
            ?? $schoolYears->first();
    }

    private static function sinceFirstEntry(User $user, CarbonImmutable $thisWeek): self
    {
        return new self('year', self::firstEntryDay($user, $thisWeek), $thisWeek->addWeek());
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
