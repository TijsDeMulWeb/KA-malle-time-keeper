<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(Role::Admin->value));
    }

    public function student(?SchoolClass $schoolClass = null, bool $withClass = true): static
    {
        return $this->afterCreating(function (User $user) use ($schoolClass, $withClass) {
            $user->assignRole(Role::Student->value);

            if ($withClass) {
                $user->schoolClasses()->attach($schoolClass ?? SchoolClass::factory()->create());
            }
        });
    }

    public function invited(): static
    {
        return $this->state(fn () => [
            'password' => null,
            'invited_at' => now(),
        ]);
    }
}
