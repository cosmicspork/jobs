<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'status' => 'generating',
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => 'ready',
            'resume_path' => 'resumes/test.pdf',
            'cover_letter_path' => 'cover-letters/test.pdf',
        ]);
    }
}
