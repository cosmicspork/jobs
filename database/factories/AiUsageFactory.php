<?php

namespace Database\Factories;

use App\Models\AiUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $model = fake()->randomElement(['anthropic/claude-sonnet-4-6', 'anthropic/claude-haiku-4-5']);
        $agent = fake()->randomElement(['JobScorerAgent', 'CoverLetterAgent', 'ResumeTailorAgent']);

        $promptTokens = fake()->numberBetween(500, 5000);
        $completionTokens = fake()->numberBetween(100, 2000);
        $cacheWriteTokens = fake()->boolean(30) ? fake()->numberBetween(1000, 10000) : 0;
        $cacheReadTokens = fake()->boolean(50) ? fake()->numberBetween(500, 8000) : 0;

        $pricing = AiUsage::PRICING[$model];
        $cost = ($promptTokens / 1_000_000) * $pricing['input']
            + ($completionTokens / 1_000_000) * $pricing['output']
            + ($cacheWriteTokens / 1_000_000) * $pricing['cacheWrite']
            + ($cacheReadTokens / 1_000_000) * $pricing['cacheRead'];

        return [
            'agent' => $agent,
            'provider' => 'anthropic',
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cache_write_tokens' => $cacheWriteTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'reasoning_tokens' => 0,
            'cost' => $cost,
        ];
    }
}
