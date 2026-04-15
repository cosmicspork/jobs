<?php

use App\Models\Listing;
use App\Models\User;

beforeEach(function () {
    $this->user = login(User::factory()->create([
        'name' => 'Josh Bowen',
        'title' => 'Engineering Manager',
        'summary' => 'A tailored summary.',
        'skills' => ['Team Building', 'PHP'],
        'experience' => [
            [
                'role' => 'Software Developer',
                'company' => 'University of Nebraska System',
                'period' => 'June 2022 - Present',
                'highlights' => ['Led AI tooling task force for a 50-person org.'],
            ],
        ],
        'education' => [
            'M.S. in IT Innovation — University of Nebraska at Omaha, 2023',
        ],
        'experience_years' => '9+',
        'preferences' => ['salary_min' => 120000],
    ]));
});

it('renders the resume template with structured experience', function () {
    $listing = Listing::factory()->create();

    $html = view('resume.base', [
        'profile' => $this->user->getProfileData(),
        'summary' => 'A tailored professional summary for testing.',
        'skills' => ['PHP', 'Laravel', 'People Management'],
        'experience' => [
            [
                'role' => 'Software Developer',
                'company' => 'University of Nebraska System',
                'period' => 'June 2022 - Present',
                'highlights' => [
                    'Led AI tooling task force for a 50-person org.',
                    'Designed a centralized integrations framework in Laravel.',
                ],
            ],
            [
                'role' => 'Co-Founder & CTO',
                'company' => 'PakPak Inc',
                'period' => 'March 2018 - March 2019',
                'highlights' => [
                    'Built multiple prototypes and a beta product.',
                ],
            ],
        ],
        'listing' => $listing,
    ])->render();

    expect($html)
        ->toContain('Josh Bowen')
        ->toContain('A tailored professional summary for testing.')
        ->toContain('PHP')
        ->toContain('Software Developer')
        ->toContain('University of Nebraska System')
        ->toContain('June 2022 - Present')
        ->toContain('Led AI tooling task force')
        ->toContain('Co-Founder &amp; CTO')
        ->toContain('PakPak Inc')
        ->toContain('Education');
});

it('includes education from profile', function () {
    $listing = Listing::factory()->create();

    $html = view('resume.base', [
        'profile' => $this->user->getProfileData(),
        'summary' => 'Test summary.',
        'skills' => ['PHP'],
        'experience' => [
            [
                'role' => 'Developer',
                'company' => 'Test Corp',
                'period' => '2020 - Present',
                'highlights' => ['Did things.'],
            ],
        ],
        'listing' => $listing,
    ])->render();

    expect($html)->toContain('M.S. in IT Innovation');
});
