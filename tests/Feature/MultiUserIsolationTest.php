<?php

use App\Models\AiUsage;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;

beforeEach(function () {
    $this->user = login();
    $this->otherUser = User::factory()->create();
});

it('only shows listings belonging to the authenticated user in the table', function () {
    $myListing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $myListing->id,
        'user_id' => $this->user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $otherListing = Listing::factory()->create(['title' => 'Other User Listing']);
    ListingUser::create([
        'listing_id' => $otherListing->id,
        'user_id' => $this->otherUser->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $this->get(route('filament.admin.resources.listings.index'))
        ->assertSee($myListing->title)
        ->assertDontSee('Other User Listing');
});

it('scopes applications to the authenticated user', function () {
    $listing = Listing::factory()->create();

    Application::factory()->create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
    ]);
    Application::factory()->create([
        'listing_id' => $listing->id,
        'user_id' => $this->otherUser->id,
    ]);

    expect($this->user->applications)->toHaveCount(1)
        ->and($this->user->applications->first()->user_id)->toBe($this->user->id);
});

it('scopes ai usage to the authenticated user', function () {
    AiUsage::factory()->create(['user_id' => $this->user->id]);
    AiUsage::factory()->create(['user_id' => $this->user->id]);
    AiUsage::factory()->create(['user_id' => $this->otherUser->id]);

    expect($this->user->aiUsages)->toHaveCount(2);
});

it('returns only the authenticated users pivot data for a shared listing', function () {
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'relevance' => Relevance::Relevant,
        'starred_at' => now(),
        'scored_at' => now(),
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->otherUser->id,
        'relevance' => Relevance::Irrelevant,
        'scored_at' => now(),
    ]);

    $pivot = ListingUser::forUserListing($this->user->id, $listing->id);

    expect($pivot)->not->toBeNull()
        ->and($pivot->relevance)->toBe(Relevance::Relevant)
        ->and($pivot->starred_at)->not->toBeNull();

    $otherPivot = ListingUser::forUserListing($this->otherUser->id, $listing->id);

    expect($otherPivot->relevance)->toBe(Relevance::Irrelevant)
        ->and($otherPivot->starred_at)->toBeNull();
});

it('does not show other users starred listings', function () {
    $listing = Listing::factory()->create(['title' => 'Starred By Other']);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->otherUser->id,
        'starred_at' => now(),
        'scored_at' => now(),
    ]);

    $this->get(route('filament.admin.resources.listings.index'))
        ->assertDontSee('Starred By Other');
});
