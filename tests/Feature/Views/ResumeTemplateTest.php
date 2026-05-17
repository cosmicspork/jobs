<?php

use App\Models\Listing;
use App\Models\User;

beforeEach(function () {
    $this->user = login(User::factory()->create([
        'name' => 'Test User',
        'summary' => 'A tailored summary.',
        'skills' => ['Team Building', 'PHP'],
        'experience' => [
            [
                'role' => 'Software Developer',
                'company' => 'Acme Industries',
                'period' => 'June 2022 - Present',
                'highlights' => ['Led tooling task force for a 50-person org.'],
            ],
        ],
        'education' => [
            'M.S. in Computer Science — Example University, 2023',
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
                'company' => 'Acme Industries',
                'period' => 'June 2022 - Present',
                'highlights' => [
                    'Led tooling task force for a 50-person org.',
                    'Designed a centralized integrations framework in Laravel.',
                ],
            ],
            [
                'role' => 'Co-Founder & CTO',
                'company' => 'Initech',
                'period' => 'March 2018 - March 2019',
                'highlights' => [
                    'Built multiple prototypes and a beta product.',
                ],
            ],
        ],
        'listing' => $listing,
    ])->render();

    expect($html)
        ->toContain('Test User')
        ->toContain('A tailored professional summary for testing.')
        ->toContain('PHP')
        ->toContain('Software Developer')
        ->toContain('Acme Industries')
        ->toContain('June 2022 - Present')
        ->toContain('Led tooling task force')
        ->toContain('Co-Founder &amp; CTO')
        ->toContain('Initech')
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

    expect($html)->toContain('M.S. in Computer Science');
});
