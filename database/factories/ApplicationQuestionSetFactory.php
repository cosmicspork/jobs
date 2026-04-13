<?php

namespace Database\Factories;

use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationQuestionSet>
 */
class ApplicationQuestionSetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'user_id' => User::factory(),
            'status' => 'draft',
        ];
    }

    public function withoutListing(): static
    {
        return $this->state(fn () => [
            'listing_id' => null,
        ]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => [
            'status' => 'reviewed',
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => 'finalized',
        ]);
    }
}
