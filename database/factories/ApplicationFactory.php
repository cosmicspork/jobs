<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
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
            'user_id' => User::factory(),
            'status' => 'generating',
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => [
            'status' => 'ready',
            'resume_content' => [
                'summary' => 'A tailored professional summary.',
                'skills' => ['PHP', 'Laravel'],
                'experience' => [
                    [
                        'role' => 'Software Engineer',
                        'company' => 'Acme Industries',
                        'period' => '2022 - Present',
                        'highlights' => ['Built things.'],
                    ],
                ],
                'education' => [
                    [
                        'qualification' => 'B.S.',
                        'institution' => 'Example University',
                        'field_of_study' => 'Computer Science',
                        'period' => '2010 - 2014',
                        'highlights' => [],
                    ],
                ],
                'keyword_matches' => ['Laravel'],
            ],
            'cover_letter_content' => [
                'subject_line' => 'Senior Engineer',
                'body' => "First paragraph.\n\nSecond paragraph.",
                'word_count' => 12,
                'posting_detail_referenced' => 'queue layer',
            ],
        ]);
    }
}
