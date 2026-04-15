<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Engineering manager profile.
     */
    public function manager(): static
    {
        return $this->state([
            'title' => 'Engineering Manager',
            'experience_years' => '12+',
            'summaries' => [
                'em' => 'Engineering manager with 12+ years building and leading teams across backend and platform engineering. Experienced hiring, coaching, and shipping large cross-functional initiatives.',
                'ic' => 'Senior backend engineer with deep experience in distributed systems, API design, and developer tooling.',
            ],
            'leadership_skills' => [
                'Hiring & Coaching',
                'Org Design',
                'Cross-functional Leadership',
                'Technical Strategy',
                'Mentorship',
            ],
            'technical_depth' => [
                'Languages' => 'Go, PHP, Python, TypeScript',
                'Infrastructure' => 'AWS, Kubernetes, Terraform',
                'Databases' => 'PostgreSQL, Redis, DynamoDB',
            ],
            'experience' => [
                [
                    'role' => 'Director of Engineering',
                    'company' => 'Acme Corp',
                    'period' => 'Jan 2022 - Present',
                    'highlights' => [
                        'Scaled engineering org from 15 to 45',
                        'Led platform migration to Kubernetes',
                        'Launched internal developer platform',
                    ],
                ],
                [
                    'role' => 'Engineering Manager',
                    'company' => 'Widgets Inc',
                    'period' => 'Jun 2018 - Dec 2021',
                    'highlights' => [
                        'Managed 3 teams (12 engineers)',
                        'Drove 99.99% uptime for payments platform',
                    ],
                ],
            ],
            'education' => ['B.S. Computer Science, State University'],
            'preferences' => [
                'remote' => true,
                'salary_min' => 220000,
                'locations' => ['Remote', 'Austin, TX'],
            ],
        ]);
    }

    /**
     * Individual contributor (senior engineer) profile.
     */
    public function ic(): static
    {
        return $this->state([
            'title' => 'Senior Software Engineer',
            'experience_years' => '8+',
            'summaries' => [
                'em' => 'Tech lead experienced guiding small teams through ambiguous problems, though prefers hands-on IC work.',
                'ic' => 'Senior software engineer with 8+ years of experience in full-stack web development, focused on Laravel, TypeScript, and developer productivity.',
            ],
            'leadership_skills' => [
                'Tech Lead',
                'Code Review',
                'Mentorship',
            ],
            'technical_depth' => [
                'Languages' => 'PHP, TypeScript, Go, SQL',
                'Frameworks' => 'Laravel, React, Livewire, Next.js',
                'Tooling' => 'Docker, GitHub Actions, Playwright',
            ],
            'experience' => [
                [
                    'role' => 'Senior Software Engineer',
                    'company' => 'Cloudy SaaS',
                    'period' => 'Mar 2021 - Present',
                    'highlights' => [
                        'Rebuilt billing pipeline to cut reconciliation time 80%',
                        'Owned Livewire-based admin panel used by 50+ internal users',
                    ],
                ],
                [
                    'role' => 'Software Engineer',
                    'company' => 'Startup.io',
                    'period' => 'Aug 2017 - Feb 2021',
                    'highlights' => [
                        'Shipped first version of multi-tenant API',
                        'Introduced Pest testing across backend',
                    ],
                ],
            ],
            'education' => ['B.S. Computer Science, Local University'],
            'preferences' => [
                'remote' => true,
                'salary_min' => 175000,
                'locations' => ['Remote'],
            ],
        ]);
    }
}
