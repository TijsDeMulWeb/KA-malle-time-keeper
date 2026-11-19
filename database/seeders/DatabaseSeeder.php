<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        User::factory()->create([
            'name' => config('time-keeper.user.name'),
            'email' => config('time-keeper.user.email'),
            'password' => config('time-keeper.user.password'),
        ]);
    }
}
