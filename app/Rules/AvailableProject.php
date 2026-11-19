<?php

namespace App\Rules;

use App\Models\Project;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AvailableProject implements ValidationRule
{
    public function __construct(
        private User $user,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Project::availableTo($this->user)->whereKey($value)->exists()) {
            $fail('Kies een geldig project.');
        }
    }
}
