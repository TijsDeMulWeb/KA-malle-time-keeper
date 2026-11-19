<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class Impersonation
{
    private static string $sessionKey = 'impersonator_id';

    public static function isActive(): bool
    {
        return session()->has(self::$sessionKey);
    }

    public static function impersonator(): ?User
    {
        $impersonatorId = session(self::$sessionKey);

        return $impersonatorId ? User::find($impersonatorId) : null;
    }

    public static function start(User $impersonator, User $user): void
    {
        StudentView::stop();

        session()->put(self::$sessionKey, $impersonator->id);
        session()->forget('auth.password_confirmed_at');

        Auth::guard('web')->login($user);
    }

    public static function stop(): ?User
    {
        $impersonator = self::impersonator();

        session()->forget([self::$sessionKey, 'auth.password_confirmed_at']);

        if ($impersonator) {
            Auth::guard('web')->login($impersonator);
        }

        return $impersonator;
    }
}
