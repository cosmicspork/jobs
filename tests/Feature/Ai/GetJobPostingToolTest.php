<?php

use App\Ai\Tools\GetJobPosting;
use App\Models\Listing;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    login();
});

it('returns listing details as json', function () {
    $listing = Listing::factory()->create([
        'title' => 'Laravel Developer',
        'company' => 'TestCo',
    ]);

    $tool = new GetJobPosting;
    $result = $tool->handle(new Request(['listing_id' => $listing->id]));

    $data = json_decode($result, true);

    expect($data)->toBeArray()
        ->and($data['title'])->toBe('Laravel Developer')
        ->and($data['company'])->toBe('TestCo')
        ->and($data['id'])->toBe($listing->id);
});

it('throws when listing not found', function () {
    $tool = new GetJobPosting;

    expect(fn () => $tool->handle(new Request(['listing_id' => 'nonexistent'])))
        ->toThrow(ModelNotFoundException::class);
});
