<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Relevance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->jobTitle(),
            'company' => fake()->company(),
            'url' => fake()->unique()->url(),
            'description' => fake()->paragraphs(3, true),
            'salary_min' => fake()->optional()->numberBetween(80000, 120000),
            'salary_max' => fake()->optional()->numberBetween(120000, 200000),
            'remote' => fake()->boolean(70),
            'board' => fake()->randomElement(['hn', 'larajobs']),
            'raw_data' => [],
            'scraped_at' => now(),
        ];
    }

    public function scored(Relevance $relevance = Relevance::Relevant): static
    {
        return $this->state(fn () => [
            'relevance' => $relevance,
            'score_data' => [
                'matched_skills' => ['PHP', 'Laravel'],
                'gaps' => ['Go'],
                'reasoning' => 'Good match for a Laravel developer.',
            ],
            'scored_at' => now(),
        ]);
    }
}
