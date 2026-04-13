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
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $application = Application::generateResume($listing, $user);

    expect($application->listing_id)->toBe($listing->id)
        ->and($application->user_id)->toBe($user->id)
        ->and($application->status)->toBe(ApplicationStatus::Generating);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 1
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume);
    });
});

it('generates cover letter only', function () {
    Bus::fake();

    $user = login();
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $application = Application::generateCoverLetter($listing, $user);

    expect($application->listing_id)->toBe($listing->id);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 1
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('generates both resume and cover letter', function () {
    Bus::fake();

    $user = login();
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    Application::generateBoth($listing, $user);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume)
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('reuses existing application for same listing and user', function () {
    Bus::fake();

    $user = login();
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $first = Application::generateResume($listing, $user);
    $second = Application::generateCoverLetter($listing, $user);

    expect($first->id)->toBe($second->id)
        ->and(Application::count())->toBe(1);
});

it('cascades delete from listing', function () {
    $listing = Listing::factory()->create();
    Application::factory()->count(2)->create(['listing_id' => $listing->id]);

    $listing->delete();

    expect(Application::count())->toBe(0);
});
