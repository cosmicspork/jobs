<?php

use App\Models\User;

it('has all required top-level keys from getProfileData', function () {
    $user = User::factory()->create([
        'summaries' => ['em' => 'EM summary', 'ic' => 'IC summary'],
        'leadership_skills' => ['Team Building', 'Mentoring'],
        'technical_depth' => ['languages' => ['PHP'], 'frameworks' => ['Laravel']],
        'experience' => [['role' => 'Dev', 'company' => 'TestCo']],
        'education' => [['degree' => 'BS CS']],
        'experience_years' => '9+',
        'preferences' => ['salary_min' => 120000],
    ]);

    $profile = $user->getProfileData();

    expect($profile)->toHaveKeys([
        'name',
        'email',
        'summaries',
        'leadership_skills',
        'experience',
        'education',
        'technical_depth',
        'experience_years',
        'preferences',
    ]);
});

it('has em and ic summaries', function () {
    $user = User::factory()->create([
        'summaries' => ['em' => 'EM summary text', 'ic' => 'IC summary text'],
    ]);

    $summaries = $user->getProfileData()['summaries'];

    expect($summaries)->toHaveKeys(['em', 'ic'])
        ->and($summaries['em'])->toBeString()->not->toBeEmpty()
        ->and($summaries['ic'])->toBeString()->not->toBeEmpty();
});

it('has leadership skills as a non-empty array', function () {
    $user = User::factory()->create([
        'leadership_skills' => ['Team Building', 'Mentoring'],
    ]);

    expect($user->getProfileData()['leadership_skills'])
        ->toBeArray()
        ->not->toBeEmpty();
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

it('has experience years and salary minimum', function () {
    $user = User::factory()->create([
        'experience_years' => '9+',
        'preferences' => ['salary_min' => 120000],
    ]);

    $profile = $user->getProfileData();

    expect($profile['experience_years'])->toBe('9+')
        ->and($profile['preferences']['salary_min'])->toBe(120000);
});

it('has technical depth categories', function () {
    $user = User::factory()->create([
        'technical_depth' => [
            'languages' => ['PHP'],
            'frameworks' => ['Laravel'],
            'laravel_ecosystem' => ['Livewire'],
            'databases' => ['MySQL'],
            'devops' => ['Docker'],
            'cloud' => ['AWS'],
        ],
    ]);

    $depth = $user->getProfileData()['technical_depth'];

    expect($depth)->toHaveKeys([
        'languages',
        'frameworks',
        'laravel_ecosystem',
        'databases',
        'devops',
        'cloud',
    ]);
});
