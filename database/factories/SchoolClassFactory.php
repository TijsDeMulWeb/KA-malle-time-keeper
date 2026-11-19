<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_year_id' => fn () => $this->currentSchoolYear()->id,
            'name' => fake()->unique()->bothify('#?'),
        ];
    }

    private function currentSchoolYear(): SchoolYear
    {
        $startYear = now()->subMonths(8)->year;
        $endYear = $startYear + 1;

        return SchoolYear::firstWhere('name', "{$startYear}-{$endYear}")
            ?? SchoolYear::factory()->current()->create();
    }
}
