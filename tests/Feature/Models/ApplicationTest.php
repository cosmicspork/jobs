<?php

use App\Models\Application;
use App\Models\Listing;

it('uses ulids as primary keys', function () {
    $application = Application::factory()->create();

    expect($application->id)->toHaveLength(26);
});

it('belongs to a listing', function () {
    $application = Application::factory()->create();

    expect($application->listing)->toBeInstanceOf(Listing::class);
});

it('defaults to generating status', function () {
    $application = Application::factory()->create();

    expect($application->status)->toBe('generating');
});

it('can be marked as ready', function () {
    $application = Application::factory()->ready()->create();

    expect($application->status)->toBe('ready')
        ->and($application->resume_path)->not->toBeNull()
        ->and($application->cover_letter_path)->not->toBeNull();
});

it('cascades delete from listing', function () {
    $listing = Listing::factory()->create();
    Application::factory()->count(2)->create(['listing_id' => $listing->id]);

    $listing->delete();

    expect(Application::count())->toBe(0);
});
