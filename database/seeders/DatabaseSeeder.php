<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\SchoolYear;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => config('time-keeper.admin.email')],
            [
                'name' => config('time-keeper.admin.name'),
                'password' => config('time-keeper.admin.password'),
            ],
        );

        $admin->assignRole(Role::Admin->value);

        $this->createCurrentSchoolYear();
    }

    private function createCurrentSchoolYear(): void
    {
        $startYear = now()->subMonths(8)->year;
        $endYear = $startYear + 1;

        SchoolYear::firstOrCreate(
            ['name' => "{$startYear}-{$endYear}"],
            [
                'starts_on' => "{$startYear}-09-01",
                'ends_on' => "{$endYear}-08-31",
            ],
        );
    }
}
