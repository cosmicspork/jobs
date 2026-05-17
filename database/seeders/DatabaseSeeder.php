<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::factory()->manager()->create([
            'name' => 'Admin Manager',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        $test = User::factory()->ic()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $users = [$admin, $test];

        $this->callWith(ListingSeeder::class, ['users' => $users]);
        $this->callWith(AiUsageSeeder::class, ['users' => $users]);
    }
}
