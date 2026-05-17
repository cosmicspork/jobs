<?php

namespace Database\Seeders;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiUsageSeeder extends Seeder
{
    /**
     * @param  array<int, User>  $users
     */
    public function run(array $users): void
    {
        // Spread usage across the last 30 days for realistic chart data.
        // JobScorer runs most often (every listing), then resume/cover letter only for applications.
        $agents = [
            'JobScorerAgent' => ['weight' => 60, 'model' => 'claude-haiku-4-5'],
            'ResumeTailorAgent' => ['weight' => 20, 'model' => 'claude-sonnet-4-6'],
            'CoverLetterAgent' => ['weight' => 20, 'model' => 'claude-sonnet-4-6'],
        ];

        $agentPool = collect($agents)->flatMap(
            fn (array $config, string $name) => array_fill(0, $config['weight'], $name)
        )->all();

        $userIds = collect($users)->pluck('id')->all();

        foreach (range(29, 0) as $daysAgo) {
            $isWeekday = ! now()->subDays($daysAgo)->isWeekend();
            $dailyRequests = $isWeekday ? fake()->numberBetween(8, 20) : fake()->numberBetween(2, 6);

            for ($i = 0; $i < $dailyRequests; $i++) {
                $agent = fake()->randomElement($agentPool);

                AiUsage::factory()->create([
                    'user_id' => fake()->randomElement($userIds),
                    'agent' => $agent,
                    'model' => $agents[$agent]['model'],
                    'created_at' => now()->subDays($daysAgo)->setTime(
                        fake()->numberBetween(8, 22),
                        fake()->numberBetween(0, 59),
                    ),
                ]);
            }
        }
    }
}
