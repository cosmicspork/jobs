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
            'is_admin' => false,
            'digest_enabled' => false,
            'digest_time' => '08:00',
            'timezone' => 'America/Chicago',
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
     * Engineering manager profile — identity only. Use ->afterCreating(...) or attach
     * a TargetProfileFactory::manager() target separately if scoring needs to run.
     */
    public function manager(): static
    {
        return $this->state([
            'summary' => 'Engineering manager with 12+ years building and leading teams across backend and platform engineering. Experienced hiring, coaching, and shipping large cross-functional initiatives.',
            'skills' => [
                'Hiring & Coaching',
                'Org Design',
                'Cross-functional Leadership',
                'Technical Strategy',
                'Mentorship',
                'Go', 'PHP', 'Python', 'TypeScript',
                'AWS', 'Kubernetes', 'Terraform',
                'PostgreSQL', 'Redis', 'DynamoDB',
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
            'education' => [
                [
                    'qualification' => 'B.S.',
                    'institution' => 'State University',
                    'field_of_study' => 'Computer Science',
                    'period' => '2008 - 2012',
                    'highlights' => [],
                ],
            ],
        ])->afterCreating(function (User $user): void {
            TargetProfileFactory::new()->manager()->for($user)->create();
        });
    }

    /**
     * Individual contributor profile — identity + a senior IC target.
     */
    public function ic(): static
    {
        return $this->state([
            'summary' => 'Senior software engineer with 8+ years of experience in full-stack web development, focused on Laravel, TypeScript, and developer productivity.',
            'skills' => [
                'PHP', 'TypeScript', 'Go', 'SQL',
                'Laravel', 'React', 'Livewire', 'Next.js',
                'Docker', 'GitHub Actions', 'Playwright',
                'Tech Lead', 'Code Review', 'Mentorship',
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
            'education' => [
                [
                    'qualification' => 'B.S.',
                    'institution' => 'Local University',
                    'field_of_study' => 'Computer Science',
                    'period' => '2013 - 2017',
                    'highlights' => [
                        'Capstone: open-source contributions to a Laravel package',
                    ],
                ],
            ],
        ])->afterCreating(function (User $user): void {
            TargetProfileFactory::new()->ic()->for($user)->create();
        });
    }
}
