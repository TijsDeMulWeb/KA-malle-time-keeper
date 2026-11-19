<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Student => 'Student',
        };
    }

    /** @return array<int, Permission> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Student => [],
        };
    }
}
