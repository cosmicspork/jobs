<?php

use App\ApplicationStatus;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Illuminate\Support\Facades\Bus;

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

    expect($application->status)->toBe(ApplicationStatus::Generating);
});

it('can be marked as ready', function () {
    $application = Application::factory()->ready()->create();

    expect($application->status)->toBe(ApplicationStatus::Ready)
        ->and($application->resume_path)->not->toBeNull()
        ->and($application->cover_letter_path)->not->toBeNull();
});

it('generates resume only', function () {
    Bus::fake();

    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $application = Application::generateResume($listing, $user, $target);

    expect($application->listing_id)->toBe($listing->id)
        ->and($application->user_id)->toBe($user->id)
        ->and($application->target_profile_id)->toBe($target->id)
        ->and($application->status)->toBe(ApplicationStatus::Generating);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 1
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume);
    });
});

it('generates cover letter only', function () {
    Bus::fake();

    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $application = Application::generateCoverLetter($listing, $user, $target);

    expect($application->listing_id)->toBe($listing->id);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 1
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('generates both resume and cover letter', function () {
    Bus::fake();

    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    Application::generateBoth($listing, $user, $target);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume)
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('reuses existing application for same listing, user, and target', function () {
    Bus::fake();

    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $first = Application::generateResume($listing, $user, $target);
    $second = Application::generateCoverLetter($listing, $user, $target);

    expect($first->id)->toBe($second->id)
        ->and(Application::count())->toBe(1);
});

it('creates separate applications for different targets', function () {
    Bus::fake();

    $user = login();
    $targetA = targetFor($user, ['name' => 'EM']);
    $targetB = targetFor($user, ['name' => 'IC']);
    $listing = Listing::factory()->create();

    foreach ([$targetA, $targetB] as $t) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $user->id,
            'target_profile_id' => $t->id,
            'relevance' => Relevance::Relevant,
            'scored_at' => now(),
        ]);
    }

    $emApp = Application::generateResume($listing, $user, $targetA);
    $icApp = Application::generateResume($listing, $user, $targetB);

    expect($emApp->id)->not->toBe($icApp->id)
        ->and(Application::count())->toBe(2);
});

it('cascades delete from listing', function () {
    $listing = Listing::factory()->create();
    Application::factory()->count(2)->create(['listing_id' => $listing->id]);

    $listing->delete();

    expect(Application::count())->toBe(0);
});
