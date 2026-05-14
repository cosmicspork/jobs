<?php

use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;

it('refuses to run without --user', function () {
    $this->artisan('listings:backfill-pivots', ['--since' => '2026-04-30'])
        ->expectsOutputToContain('Both --user and --since are required.')
        ->assertFailed();
});

it('refuses to run without --since', function () {
    $user = User::factory()->ic()->create();

    $this->artisan('listings:backfill-pivots', ['--user' => $user->id])
        ->expectsOutputToContain('Both --user and --since are required.')
        ->assertFailed();
});

it('errors clearly when the user id does not exist', function () {
    $this->artisan('listings:backfill-pivots', ['--user' => 9999, '--since' => '2026-04-30'])
        ->expectsOutputToContain('No user with id 9999.')
        ->assertFailed();
});

it('errors clearly when --since cannot be parsed', function () {
    $user = User::factory()->ic()->create();

    $this->artisan('listings:backfill-pivots', ['--user' => $user->id, '--since' => 'not-a-date'])
        ->expectsOutputToContain('Could not parse --since=not-a-date as a date.')
        ->assertFailed();
});

it('creates pivots for listings scraped since the cutoff and marks them digested', function () {
    $user = User::factory()->ic()->create();
    $extraTarget = targetFor($user, ['name' => 'Second target']);
    $activeTargets = $user->targetProfiles()->where('is_active', true)->count();

    $recent = Listing::factory()->count(2)->create(['scraped_at' => now()->subDays(3)]);
    $old = Listing::factory()->create(['scraped_at' => now()->subDays(30)]);

    $this->artisan('listings:backfill-pivots', [
        '--user' => $user->id,
        '--since' => now()->subDays(7)->format('Y-m-d'),
    ])->assertSuccessful();

    $pivots = ListingUser::query()->where('user_id', $user->id)->get();

    expect($pivots)->toHaveCount(2 * $activeTargets)
        ->and($pivots->every(fn (ListingUser $p) => $p->scored_at === null))->toBeTrue()
        ->and($pivots->every(fn (ListingUser $p) => $p->digested_at !== null))->toBeTrue()
        ->and($pivots->pluck('listing_id')->unique()->values()->all())
        ->toEqualCanonicalizing($recent->pluck('id')->all())
        ->and(ListingUser::query()->where('listing_id', $old->id)->exists())->toBeFalse()
        ->and($extraTarget)->not->toBeNull();
});

it('skips inactive targets and existing pivots so it is idempotent', function () {
    $user = User::factory()->ic()->create();
    targetFor($user, ['name' => 'Inactive', 'is_active' => false]);
    $listing = Listing::factory()->create(['scraped_at' => now()->subDays(2)]);

    $this->artisan('listings:backfill-pivots', [
        '--user' => $user->id,
        '--since' => now()->subDays(7)->format('Y-m-d'),
    ])->assertSuccessful();

    $countAfterFirstRun = ListingUser::query()->where('user_id', $user->id)->count();

    $this->artisan('listings:backfill-pivots', [
        '--user' => $user->id,
        '--since' => now()->subDays(7)->format('Y-m-d'),
    ])->assertSuccessful();

    expect(ListingUser::query()->where('user_id', $user->id)->count())->toBe($countAfterFirstRun)
        ->and($countAfterFirstRun)->toBe(1); // one active target × one in-window listing
});

it('exits cleanly when the user has no active targets', function () {
    $user = User::factory()->create();
    Listing::factory()->create(['scraped_at' => now()->subDays(2)]);

    $this->artisan('listings:backfill-pivots', [
        '--user' => $user->id,
        '--since' => now()->subDays(7)->format('Y-m-d'),
    ])
        ->expectsOutputToContain('has no active targets')
        ->assertSuccessful();

    expect(ListingUser::query()->where('user_id', $user->id)->count())->toBe(0);
});
