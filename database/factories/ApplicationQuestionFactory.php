<?php

namespace Database\Factories;

use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationQuestion>
 */
class ApplicationQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_set_id' => ApplicationQuestionSet::factory(),
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
        ];
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'feedback' => fake()->paragraph(),
            'grammar_corrections' => fake()->sentence(),
            'suggested_answer' => fake()->paragraph(),
        ]);
    }

    public function finalized(): static
    {
        return $this->reviewed()->state(fn () => [
            'final_answer' => fake()->paragraph(),
        ]);
    }
}
