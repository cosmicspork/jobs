<?php

use App\Jobs\ScoreListing;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = login();
});

it('dispatches scoring jobs for unscored listing-user pairs', function () {
    Queue::fake();

    $listings = Listing::factory()->count(3)->create();
    foreach ($listings as $listing) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
        ]);
    }

    $this->artisan('jobs:score')
        ->assertSuccessful();

    Queue::assertPushed(ScoreListing::class, 3);
});

it('skips already scored listing-user pairs', function () {
    Queue::fake();

    $scoredListing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $scoredListing->id,
        'user_id' => $this->user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $unscoredListing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $unscoredListing->id,
        'user_id' => $this->user->id,
    ]);

    $this->artisan('jobs:score')
        ->assertSuccessful();

    Queue::assertPushed(ScoreListing::class, 1);
});

it('reports when no unscored listings exist', function () {
    Queue::fake();

    $this->artisan('jobs:score')
        ->expectsOutputToContain('No unscored listings found');
});
