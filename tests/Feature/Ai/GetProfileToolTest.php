<?php

use App\Ai\Tools\GetProfile;
use Laravel\Ai\Tools\Request;

it('returns the profile config as json', function () {
    $tool = new GetProfile;
    $result = $tool->handle(new Request([]));

    $data = json_decode($result, true);

    expect($data)->toBeArray()
        ->and($data['name'])->not->toBeEmpty()
        ->and($data['skills'])->toBeArray()
        ->and($data['skills'])->toContain('PHP')
        ->and($data['skills'])->toContain('Laravel');
});
