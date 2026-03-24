<?php

use App\Models\Application;
use App\Models\Listing;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

it('uses ulids as primary keys', function () {
    $listing = Listing::factory()->create();

    expect($listing->id)->toHaveLength(26);
});

it('has many applications', function () {
    $listing = Listing::factory()->create();
    Application::factory()->count(3)->create(['listing_id' => $listing->id]);

    expect($listing->applications)->toHaveCount(3);
});

it('casts score_data to array', function () {
    $listing = Listing::factory()->scored()->create();

    expect($listing->score_data)->toBeArray()
        ->and($listing->score_data['matched_skills'])->toContain('PHP');
});

it('casts remote to boolean', function () {
    $listing = Listing::factory()->create(['remote' => true]);

    expect($listing->remote)->toBeTrue();
});

it('casts scored_at to datetime', function () {
    $listing = Listing::factory()->scored()->create();

    expect($listing->scored_at)->toBeInstanceOf(Carbon::class);
});

it('deduplicates listings by url', function () {
    Listing::factory()->create(['url' => 'https://example.com/job/1']);

    expect(fn () => Listing::factory()->create(['url' => 'https://example.com/job/1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('toggles starred state', function () {
    $listing = Listing::factory()->create();

    expect($listing->starred_at)->toBeNull();

    $listing->toggleStarred();
    $listing->refresh();

    expect($listing->starred_at)->toBeInstanceOf(Carbon::class);

    $listing->toggleStarred();
    $listing->refresh();

    expect($listing->starred_at)->toBeNull();
});

it('can be shortlisted', function () {
    $listing = Listing::factory()->create();

    expect($listing->shortlisted_at)->toBeNull();

    $listing->shortlist();
    $listing->refresh();

    expect($listing->shortlisted_at)->toBeInstanceOf(Carbon::class);
});
