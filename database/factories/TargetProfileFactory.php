<?php

namespace Database\Factories;

use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetProfile>
 */
class TargetProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Engineering roles', 'Backend roles', 'Platform roles']),
            'positioning' => fake()->paragraph(),
            'target_titles' => ['Senior Software Engineer', 'Staff Engineer'],
            'criteria' => [
                'remote' => true,
                'salary_min' => 175000,
                'locations' => ['Remote'],
                'must_have_keywords' => [],
                'avoid_keywords' => [],
            ],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function manager(): static
    {
        return $this->state([
            'name' => 'Engineering Management',
            'positioning' => 'Targeting Series B-D engineering management roles where I can scale teams and shape engineering culture. Strongest fit when the company is past product-market fit but still small enough that org design matters.',
            'target_titles' => ['Engineering Manager', 'Director of Engineering', 'Head of Engineering', 'VP of Engineering'],
            'criteria' => [
                'remote' => true,
                'salary_min' => 220000,
                'locations' => ['Remote', 'Austin, TX'],
                'must_have_keywords' => [],
                'avoid_keywords' => ['contract', 'short-term'],
            ],
        ]);
    }

    public function ic(): static
    {
        return $this->state([
            'name' => 'Senior IC roles',
            'positioning' => 'Looking for senior or staff IC roles on small teams where I can own systems end-to-end and influence technical direction without managing.',
            'target_titles' => ['Senior Software Engineer', 'Staff Engineer', 'Principal Engineer'],
            'criteria' => [
                'remote' => true,
                'salary_min' => 175000,
                'locations' => ['Remote'],
                'must_have_keywords' => [],
                'avoid_keywords' => [],
            ],
        ]);
    }
}
