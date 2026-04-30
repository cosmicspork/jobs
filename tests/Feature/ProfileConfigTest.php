<?php

use App\Models\User;

it('returns expected keys from getProfileData', function () {
    $user = User::factory()->create([
        'summary' => 'A tailored summary.',
        'skills' => ['PHP', 'Laravel', 'Mentorship'],
        'experience' => [['role' => 'Dev', 'company' => 'TestCo']],
        'education' => ['BS CS'],
        'experience_years' => '9+',
    ]);

    $profile = $user->getProfileData();

    expect($profile)->toHaveKeys([
        'name',
        'email',
        'summary',
        'skills',
        'experience',
        'education',
        'experience_years',
    ])->and($profile)->not->toHaveKey('title');
});

it('returns skills as a flat non-empty array', function () {
    $user = User::factory()->create([
        'skills' => ['PHP', 'Laravel', 'Kubernetes', 'Mentorship'],
    ]);

    expect($user->getProfileData()['skills'])
        ->toBeArray()
        ->not->toBeEmpty()
        ->toContain('PHP')
        ->toContain('Mentorship');
});

it('returns prompts via getPrompt method', function () {
    $user = User::factory()->create([
        'prompts' => [
            'scorer' => 'Custom scorer prompt',
            'resume' => 'Custom resume prompt',
            'cover_letter' => 'Custom cover letter prompt',
            'application_questions' => 'Custom AQ prompt',
        ],
    ]);

    expect($user->getPrompt('scorer'))->toBe('Custom scorer prompt')
        ->and($user->getPrompt('resume'))->toBe('Custom resume prompt')
        ->and($user->getPrompt('cover_letter'))->toBe('Custom cover letter prompt')
        ->and($user->getPrompt('application_questions'))->toBe('Custom AQ prompt');
});

it('exposes experience_years on getProfileData', function () {
    $user = User::factory()->create([
        'experience_years' => '9+',
    ]);

    expect($user->getProfileData()['experience_years'])->toBe('9+');
});
