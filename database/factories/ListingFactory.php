<?php

namespace Database\Factories;

use App\Models\Listing;
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
            'source_url' => fn (array $attrs) => $attrs['url'],
            'description' => fake()->paragraphs(3, true),
            'salary_min' => fake()->optional()->numberBetween(80000, 120000),
            'salary_max' => fake()->optional()->numberBetween(120000, 200000),
            'remote' => fake()->boolean(70),
            'board' => fake()->randomElement(['hn', 'larajobs']),
            'raw_data' => [],
            'scraped_at' => now(),
            'enriched_at' => now(),
            'enrichment_source' => 'inline',
        ];
    }

    /**
     * State for listings that came from an enrichment-required board (e.g.
     * LaraJobs) and haven't yet been processed by EnrichListing.
     */
    public function awaitingEnrichment(): static
    {
        return $this->state(fn () => [
            'board' => 'larajobs',
            'enriched_at' => null,
            'enrichment_source' => null,
        ]);
    }
}
