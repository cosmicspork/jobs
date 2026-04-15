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
        User::factory()->manager()->create([
            'name' => 'Admin Manager',
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);

        User::factory()->ic()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            ListingSeeder::class,
            AiUsageSeeder::class,
        ]);
    }
}
