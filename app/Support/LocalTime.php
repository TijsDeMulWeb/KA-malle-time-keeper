<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class LocalTime
{
    public static function timezone(): string
    {
        return config('time-keeper.timezone');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function parse(string $date, string $time = '00:00'): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", self::timezone());
    }

    public static function toUtc(string $date, string $time): CarbonImmutable
    {
        return self::parse($date, $time)->utc();
    }

    public static function local(CarbonInterface $moment): CarbonImmutable
    {
        return $moment->toImmutable()->setTimezone(self::timezone());
    }

    public static function startOfWeek(?string $date = null): CarbonImmutable
    {
        $day = $date ? self::parse($date) : self::now();

        return $day->startOfWeek(CarbonInterface::MONDAY);
    }
}
