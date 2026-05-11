<?php

use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

it('uses ulids as primary keys', function () {
    $listing = Listing::factory()->create();

    expect($listing->id)->toHaveLength(26);
});

it('has many applications', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    Application::factory()->count(3)->create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
    ]);

    expect($listing->applications)->toHaveCount(3);
});

it('casts score_data to array on pivot', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'score_data' => [
            'matched_skills' => ['PHP', 'Laravel'],
            'gaps' => ['Go'],
            'reasoning' => 'Good match for a Laravel developer.',
        ],
        'scored_at' => now(),
    ]);

    expect($pivot->score_data)->toBeArray()
        ->and($pivot->score_data['matched_skills'])->toContain('PHP');
});

it('casts remote to boolean', function () {
    $listing = Listing::factory()->create(['remote' => true]);

    expect($listing->remote)->toBeTrue();
});

it('casts scored_at to datetime on pivot', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'scored_at' => now(),
    ]);

    expect($pivot->scored_at)->toBeInstanceOf(Carbon::class);
});

it('deduplicates listings by url', function () {
    Listing::factory()->create(['url' => 'https://example.com/job/1']);

    expect(fn () => Listing::factory()->create(['url' => 'https://example.com/job/1']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('toggles starred state on pivot', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
    ]);

    expect($pivot->starred_at)->toBeNull();

    $pivot->toggleStarred();
    $pivot->refresh();

    expect($pivot->starred_at)->toBeInstanceOf(Carbon::class);

    $pivot->toggleStarred();
    $pivot->refresh();

    expect($pivot->starred_at)->toBeNull();
});

it('toggles shortlisted state on pivot', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
    ]);

    expect($pivot->shortlisted_at)->toBeNull();

    $pivot->toggleShortlisted();
    $pivot->refresh();

    expect($pivot->shortlisted_at)->toBeInstanceOf(Carbon::class);

    $pivot->toggleShortlisted();
    $pivot->refresh();

    expect($pivot->shortlisted_at)->toBeNull();
});

it('toggles dismissed state on pivot', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
    ]);

    expect($pivot->dismissed_at)->toBeNull();

    $pivot->toggleDismissed();
    $pivot->refresh();

    expect($pivot->dismissed_at)->toBeInstanceOf(Carbon::class);

    $pivot->toggleDismissed();
    $pivot->refresh();

    expect($pivot->dismissed_at)->toBeNull();
});

it('toggles shortlisted across all of the user pivots for the listing', function () {
    $user = login();
    $targetA = targetFor($user, ['name' => 'Backend roles']);
    $targetB = targetFor($user, ['name' => 'Lead roles']);
    $listing = Listing::factory()->create();
    $pivotA = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $targetA->id,
    ]);
    $pivotB = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $targetB->id,
    ]);

    $pivotA->toggleShortlisted();

    expect($pivotA->fresh()->shortlisted_at)->not->toBeNull()
        ->and($pivotB->fresh()->shortlisted_at)->not->toBeNull();

    $pivotB->refresh()->toggleShortlisted();

    expect($pivotA->fresh()->shortlisted_at)->toBeNull()
        ->and($pivotB->fresh()->shortlisted_at)->toBeNull();
});
