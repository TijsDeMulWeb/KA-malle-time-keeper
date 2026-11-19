<?php

namespace Database\Factories;

use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2099);
        $endYear = $startYear + 1;

        return [
            'name' => "{$startYear}-{$endYear}",
            'starts_on' => "{$startYear}-09-01",
            'ends_on' => "{$endYear}-08-31",
            'closed_at' => null,
        ];
    }

    public function starting(int $startYear): static
    {
        $endYear = $startYear + 1;

        return $this->state(fn () => [
            'name' => "{$startYear}-{$endYear}",
            'starts_on' => "{$startYear}-09-01",
            'ends_on' => "{$endYear}-08-31",
        ]);
    }

    public function current(): static
    {
        return $this->starting(now()->subMonths(8)->year);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['closed_at' => now()]);
    }
}
