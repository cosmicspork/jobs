<?php

use App\Models\Listing;

it('renders the resume template with structured experience', function () {
    $listing = Listing::factory()->create();

    $html = view('resume.base', [
        'profile' => config('profile'),
        'summary' => 'A tailored professional summary for testing.',
        'skills' => ['PHP', 'Laravel', 'People Management'],
        'experience' => [
            [
                'role' => 'Software Developer',
                'company' => 'Acme Industries',
                'period' => 'June 2022 - Present',
                'highlights' => [
                    'Led AI tooling task force for a 50-person org.',
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
        ->toContain(config('profile.name'))
        ->toContain(config('profile.title'))
        ->toContain('A tailored professional summary for testing.')
        ->toContain('PHP')
        ->toContain('Software Developer')
        ->toContain('Acme Industries')
        ->toContain('June 2022 - Present')
        ->toContain('Led AI tooling task force')
        ->toContain('Co-Founder &amp; CTO')
        ->toContain('Initech')
        ->toContain('Education');
});

it('includes education from profile', function () {
    $listing = Listing::factory()->create();

    $html = view('resume.base', [
        'profile' => config('profile'),
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
