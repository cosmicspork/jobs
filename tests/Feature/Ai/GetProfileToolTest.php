<?php

use App\Ai\Tools\GetProfile;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('returns the profile data as json', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'title' => 'Senior Engineer',
        'summary' => 'A clear summary.',
        'skills' => ['PHP', 'Laravel', 'Mentorship'],
        'experience' => [['role' => 'Dev', 'company' => 'TestCo']],
        'education' => ['BS CS'],
        'experience_years' => '9+',
        'preferences' => ['salary_min' => 120000, 'role_type' => 'em'],
        'prompts' => ['scorer' => 'Score this job'],
    ]);

    $tool = new GetProfile($user);
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Test User')
        ->and($data['title'])->toBe('Senior Engineer')
        ->and($data['summary'])->toBe('A clear summary.')
        ->and($data['skills'])->toBeArray()->not->toBeEmpty()
        ->and($data['role_type'])->toBe('em');
});

it('excludes prompts from the output', function () {
    $user = User::factory()->create([
        'prompts' => ['scorer' => 'Score this job'],
    ]);

    $tool = new GetProfile($user);
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->not->toHaveKey('prompts');
});
