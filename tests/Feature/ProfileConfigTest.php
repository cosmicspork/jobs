<?php

use App\Ai\Agents\JobScorerAgent;
use App\Ai\Agents\ResumeTailorAgent;
use App\Models\User;

it('returns expected keys from getProfileData', function () {
    $user = User::factory()->create([
        'summary' => 'A tailored summary.',
        'skills' => ['PHP', 'Laravel', 'Mentorship'],
        'experience' => [['role' => 'Dev', 'company' => 'TestCo']],
        'education' => [[
            'qualification' => 'B.S.',
            'institution' => 'Test U',
            'field_of_study' => 'CS',
            'period' => '2010 - 2014',
            'highlights' => [],
        ]],
    ]);

    $profile = $user->getProfileData();

    expect($profile)->toHaveKeys([
        'name',
        'email',
        'summary',
        'skills',
        'experience',
        'education',
    ])->and($profile)->not->toHaveKey('experience_years');
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

it('composes agent instructions in static-prompt → profile → target order', function () {
    $user = User::factory()->create([
        'name' => 'Sample Candidate',
        'skills' => ['PHP'],
    ]);
    $target = targetFor($user, [
        'name' => 'Senior PHP',
        'positioning' => 'Backend engineer.',
        'target_titles' => ['Senior PHP'],
        'criteria' => ['remote' => true],
    ]);

    $instructions = (string) (new JobScorerAgent($user, $target))->instructions();

    expect($instructions)->toStartWith('You are a job matching analyst.')
        ->and($instructions)->toContain('CANDIDATE PROFILE:')
        ->and($instructions)->toContain('"name": "Sample Candidate"')
        ->and($instructions)->toContain('TARGET:')
        ->and($instructions)->toContain('"name": "Senior PHP"');

    expect(strpos($instructions, 'CANDIDATE PROFILE:'))
        ->toBeLessThan(strpos($instructions, 'TARGET:'));
});

it('is byte-identical across repeat calls for the same user + target', function () {
    $user = User::factory()->create();
    $target = targetFor($user);

    $agent = new ResumeTailorAgent($user, $target);

    expect((string) $agent->instructions())
        ->toBe((string) $agent->instructions());
});
