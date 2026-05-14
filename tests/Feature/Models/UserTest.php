<?php

use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;

it('bestTargetFor prefers higher relevance over sort_order', function () {
    $user = login();
    $relevantTarget = targetFor($user, ['name' => 'Relevant target', 'sort_order' => 5]);
    $maybeTarget = targetFor($user, ['name' => 'Maybe target', 'sort_order' => 0]);
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $relevantTarget->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $maybeTarget->id,
        'relevance' => Relevance::Maybe,
        'scored_at' => now(),
    ]);

    expect($user->bestTargetFor($listing)?->id)->toBe($relevantTarget->id);
});

it('bestTargetFor breaks relevance ties on sort_order ascending', function () {
    $user = login();
    $first = targetFor($user, ['name' => 'First', 'sort_order' => 0]);
    $second = targetFor($user, ['name' => 'Second', 'sort_order' => 1]);
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $first->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $second->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    expect($user->bestTargetFor($listing)?->id)->toBe($first->id);
});

it('bestTargetFor falls back to scored_at DESC when relevance and sort_order tie', function () {
    $user = login();
    $earlier = targetFor($user, ['name' => 'Earlier', 'sort_order' => 0]);
    $later = targetFor($user, ['name' => 'Later', 'sort_order' => 0]);
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $earlier->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subHour(),
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $later->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    expect($user->bestTargetFor($listing)?->id)->toBe($later->id);
});

it('bestTargetFor ignores inactive targets even at higher relevance', function () {
    $user = login();
    $inactive = targetFor($user, ['name' => 'Inactive', 'sort_order' => 0, 'is_active' => false]);
    $active = targetFor($user, ['name' => 'Active', 'sort_order' => 1, 'is_active' => true]);
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $inactive->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $active->id,
        'relevance' => Relevance::Maybe,
        'scored_at' => now(),
    ]);

    expect($user->bestTargetFor($listing)?->id)->toBe($active->id);
});

it('bestTargetFor falls back to first active target when nothing is scored', function () {
    $user = login();
    $first = targetFor($user, ['name' => 'First', 'sort_order' => 0]);
    targetFor($user, ['name' => 'Second', 'sort_order' => 1]);
    $listing = Listing::factory()->create();

    expect($user->bestTargetFor($listing)?->id)->toBe($first->id);
});

it('bestTargetFor uses sort_order over scored_at when relevance ties', function () {
    $user = login();
    $lowSortOrder = targetFor($user, ['name' => 'Low sort_order', 'sort_order' => 0]);
    $highSortOrder = targetFor($user, ['name' => 'High sort_order', 'sort_order' => 1]);
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $lowSortOrder->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subHour(),
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $highSortOrder->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    expect($user->bestTargetFor($listing)?->id)->toBe($lowSortOrder->id);
});
