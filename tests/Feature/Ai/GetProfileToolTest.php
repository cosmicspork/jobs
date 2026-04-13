<?php

use App\Ai\Tools\GetProfile;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('returns the profile data as json', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'summaries' => ['em' => 'EM summary', 'ic' => 'IC summary'],
        'leadership_skills' => ['Team Building', 'Mentoring'],
        'technical_depth' => ['languages' => ['PHP']],
        'experience' => [['role' => 'Dev', 'company' => 'TestCo']],
        'education' => [['degree' => 'BS CS']],
        'experience_years' => '9+',
        'preferences' => ['salary_min' => 120000],
        'prompts' => ['scorer' => 'Score this job'],
    ]);

    $tool = new GetProfile($user);
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Test User')
        ->and($data['leadership_skills'])->toBeArray()->not->toBeEmpty()
        ->and($data['summaries'])->toBeArray()
        ->and($data['summaries'])->toHaveKeys(['em', 'ic']);
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
