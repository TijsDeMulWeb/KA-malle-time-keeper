<?php

namespace App\Rules;

use App\Models\User;
use App\Support\LocalTime;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LoggableDate implements ValidationRule
{
    public function __construct(
        private User $user,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return;
        }

        $restriction = $this->user->timeLoggingRestriction(LocalTime::parse($value));

        if ($restriction) {
            $fail($restriction);
        }
    }
}
