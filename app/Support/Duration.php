<?php

namespace App\Support;

class Duration
{
    public static function format(int $minutes): string
    {
        if ($minutes <= 0) {
            return '–';
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;

        if ($hours === 0) {
            return "{$remainder}m";
        }

        if ($remainder === 0) {
            return "{$hours}u";
        }

        return "{$hours}u {$remainder}m";
    }

    public static function clock(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainder = str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);

        return "{$hours}:{$remainder}";
    }

    public static function clockWithSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = str_pad((string) intdiv($seconds % 3600, 60), 2, '0', STR_PAD_LEFT);
        $remainder = str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);

        return "{$hours}:{$minutes}:{$remainder}";
    }
}
