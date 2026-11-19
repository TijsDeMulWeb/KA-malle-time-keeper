<?php

namespace App\Support;

use App\Models\User;

class StudentView
{
    private static string $sessionKey = 'student_view';

    public static function isActive(): bool
    {
        if (! app()->bound('session')) {
            return false;
        }

        return session()->get(self::$sessionKey) === true;
    }

    public static function isActiveFor(User $user): bool
    {
        if (! self::isActive()) {
            return false;
        }

        return auth()->id() === $user->id;
    }

    public static function start(): void
    {
        session()->put(self::$sessionKey, true);
    }

    public static function stop(): void
    {
        session()->forget(self::$sessionKey);
    }
}
