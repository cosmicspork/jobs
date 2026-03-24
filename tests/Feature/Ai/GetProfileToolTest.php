<?php

use App\Ai\Tools\GetProfile;
use Laravel\Ai\Tools\Request;

it('returns the profile config as json', function () {
    $tool = new GetProfile;
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->toBeArray()
        ->and($data['name'])->not->toBeEmpty()
        ->and($data['leadership_skills'])->toBeArray()->not->toBeEmpty()
        ->and($data['summaries'])->toBeArray()
        ->and($data['summaries'])->toHaveKeys(['em', 'ic']);
});

it('excludes prompts from the output', function () {
    $tool = new GetProfile;
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->not->toHaveKey('prompts');
});
