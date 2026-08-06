<?php

use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;

beforeEach(function () {
    $this->user = login();
    $this->target = targetFor($this->user);
});

function pivotFor(Listing $listing, int $userId, string $targetId, array $overrides = []): ListingUser
{
    return ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $userId,
        'target_profile_id' => $targetId,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        ...$overrides,
    ]);
}

it('stamps applied_at from the earliest application for the listing', function () {
    $listing = Listing::factory()->create();
    $pivot = pivotFor($listing, $this->user->id, $this->target->id);

    $earliest = now()->subDays(10);
    Application::factory()->for($listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'created_at' => $earliest,
    ]);
    Application::factory()->for($listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'created_at' => now()->subDays(2),
    ]);

    test()->artisan('listings:backfill-applied-at')->assertSuccessful();

    expect($pivot->refresh()->applied_at->timestamp)->toBe($earliest->timestamp);
});

it('leaves listings without applications untouched', function () {
    $pivot = pivotFor(Listing::factory()->create(), $this->user->id, $this->target->id);

    test()->artisan('listings:backfill-applied-at')->assertSuccessful();

    expect($pivot->refresh()->applied_at)->toBeNull();
});

it('does not overwrite an applied_at that is already set', function () {
    $listing = Listing::factory()->create();
    $manual = now()->subDay();
    $pivot = pivotFor($listing, $this->user->id, $this->target->id, ['applied_at' => $manual]);

    Application::factory()->for($listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'created_at' => now()->subDays(30),
    ]);

    test()->artisan('listings:backfill-applied-at')->assertSuccessful();

    expect($pivot->refresh()->applied_at->timestamp)->toBe($manual->timestamp);
});

it('is idempotent', function () {
    $listing = Listing::factory()->create();
    $pivot = pivotFor($listing, $this->user->id, $this->target->id);
    Application::factory()->for($listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'created_at' => now()->subDays(5),
    ]);

    test()->artisan('listings:backfill-applied-at')->assertSuccessful();
    $first = $pivot->refresh()->applied_at;

    test()->artisan('listings:backfill-applied-at')->assertSuccessful();

    expect($pivot->refresh()->applied_at->timestamp)->toBe($first->timestamp);
});

it('reports without writing under --dry-run', function () {
    $listing = Listing::factory()->create();
    $pivot = pivotFor($listing, $this->user->id, $this->target->id);
    Application::factory()->for($listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);

    test()->artisan('listings:backfill-applied-at', ['--dry-run' => true])
        ->expectsOutputToContain('Would stamp applied_at on 1 pivot(s).')
        ->assertSuccessful();

    expect($pivot->refresh()->applied_at)->toBeNull();
});

it('does not stamp a pivot belonging to another user', function () {
    $listing = Listing::factory()->create();
    $other = User::factory()->create();
    $otherTarget = targetFor($other);

    $otherPivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $other->id,
        'target_profile_id' => $otherTarget->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    Application::factory()->for($listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);

    test()->artisan('listings:backfill-applied-at')->assertSuccessful();

    expect($otherPivot->refresh()->applied_at)->toBeNull();
});
