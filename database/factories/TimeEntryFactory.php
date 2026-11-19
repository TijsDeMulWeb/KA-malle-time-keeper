<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subDays(fake()->numberBetween(0, 6))->setTime(fake()->numberBetween(8, 17), 0);

        return [
            'user_id' => User::factory(),
            'project_id' => fn (array $attributes) => Project::factory()->create(['user_id' => $attributes['user_id']])->id,
            'category_id' => null,
            'description' => fake()->sentence(3),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addMinutes(fake()->randomElement([30, 60, 90, 120])),
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subMinutes(10),
            'ended_at' => null,
        ]);
    }
}
