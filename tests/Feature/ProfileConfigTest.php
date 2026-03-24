<?php

it('has all required top-level keys', function () {
    $profile = config('profile');

    expect($profile)->toHaveKeys([
        'name',
        'title',
        'email',
        'location',
        'summaries',
        'leadership_skills',
        'experience',
        'education',
        'technical_depth',
        'experience_years',
        'preferences',
        'prompts',
    ]);
});

it('has em and ic summaries', function () {
    $summaries = config('profile.summaries');

    expect($summaries)->toHaveKeys(['em', 'ic'])
        ->and($summaries['em'])->toBeString()->not->toBeEmpty()
        ->and($summaries['ic'])->toBeString()->not->toBeEmpty();
});

it('has leadership skills as a non-empty array', function () {
    expect(config('profile.leadership_skills'))
        ->toBeArray()
        ->not->toBeEmpty();
});

it('has prompts for all agents', function () {
    $prompts = config('profile.prompts');

    expect($prompts)->toHaveKeys(['scorer', 'resume', 'cover_letter'])
        ->and($prompts['scorer'])->toBeString()->not->toBeEmpty()
        ->and($prompts['resume'])->toBeString()->not->toBeEmpty()
        ->and($prompts['cover_letter'])->toBeString()->not->toBeEmpty();
});

it('has updated experience years and salary minimum', function () {
    expect(config('profile.experience_years'))->toBe('9+')
        ->and(config('profile.preferences.salary_min'))->toBe(120000);
});

it('has technical depth categories', function () {
    $depth = config('profile.technical_depth');

    expect($depth)->toHaveKeys([
        'languages',
        'frameworks',
        'laravel_ecosystem',
        'databases',
        'devops',
        'cloud',
    ]);
});
