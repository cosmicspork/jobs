<?php

use App\Ai\Tools\GetProfile;
use App\Ai\Tools\GetTargetProfile;
use App\Models\User;
use Laravel\Ai\Tools\Request;

it('returns the candidate identity as json', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'summary' => 'A clear summary.',
        'skills' => ['PHP', 'Laravel', 'Mentorship'],
        'experience' => [['role' => 'Dev', 'company' => 'TestCo']],
        'education' => ['BS CS'],
        'experience_years' => '9+',
        'prompts' => ['scorer' => 'Score this job'],
    ]);

    $tool = new GetProfile($user);
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->toBeArray()
        ->and($data['name'])->toBe('Test User')
        ->and($data['summary'])->toBe('A clear summary.')
        ->and($data['skills'])->toBeArray()->not->toBeEmpty()
        ->and($data)->not->toHaveKey('title')
        ->and($data)->not->toHaveKey('role_type')
        ->and($data)->not->toHaveKey('preferences');
});

it('excludes prompts and preferences from the output', function () {
    $user = User::factory()->create([
        'prompts' => ['scorer' => 'Score this job'],
    ]);

    $tool = new GetProfile($user);
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->not->toHaveKey('prompts')
        ->and($data)->not->toHaveKey('preferences');
});

it('GetTargetProfile returns the target name, positioning, titles, and criteria', function () {
    $user = User::factory()->create();
    $target = targetFor($user, [
        'name' => 'Engineering Management',
        'positioning' => 'Looking for EM roles at Series B-D companies.',
        'target_titles' => ['Engineering Manager', 'Director'],
        'criteria' => ['remote' => true, 'salary_min' => 220000, 'locations' => []],
    ]);

    $tool = new GetTargetProfile($target);
    $data = json_decode($tool->handle(new Request([])), true);

    expect($data['name'])->toBe('Engineering Management')
        ->and($data['positioning'])->toBe('Looking for EM roles at Series B-D companies.')
        ->and($data['target_titles'])->toContain('Engineering Manager')
        ->and($data['criteria']['remote'])->toBeTrue();
});
