<?php

use App\Jobs\ScoreListing;
use App\Mail\UserCapReached;
use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = login(User::factory()->ic()->create());
});

it('dispatches scoring jobs for unscored listing-user pairs', function () {
    Queue::fake();

    $listings = Listing::factory()->count(3)->create(['remote' => true, 'salary_max' => null]);
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

    $scoredListing = Listing::factory()->create(['remote' => true, 'salary_max' => null]);
    ListingUser::create([
        'listing_id' => $scoredListing->id,
        'user_id' => $this->user->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $unscoredListing = Listing::factory()->create(['remote' => true, 'salary_max' => null]);
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

it('skips users whose profiles are incomplete', function () {
    Queue::fake();

    $bareUser = User::factory()->create();
    $listing = Listing::factory()->create(['remote' => true, 'salary_max' => null]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $bareUser->id,
    ]);

    $this->artisan('jobs:score')->assertSuccessful();

    Queue::assertNothingPushed();
    expect(ListingUser::query()->where('user_id', $bareUser->id)->first()->scored_at)->toBeNull();
});

it('marks heuristically-filtered listings as irrelevant without dispatching scoring', function () {
    Queue::fake();

    // user wants remote; listing is not remote
    $notRemote = Listing::factory()->create(['remote' => false, 'salary_max' => null]);
    ListingUser::create([
        'listing_id' => $notRemote->id,
        'user_id' => $this->user->id,
    ]);

    // user min is 175k; listing maxes out below
    $lowSalary = Listing::factory()->create(['remote' => true, 'salary_max' => 100000]);
    ListingUser::create([
        'listing_id' => $lowSalary->id,
        'user_id' => $this->user->id,
    ]);

    $this->artisan('jobs:score')->assertSuccessful();

    Queue::assertNothingPushed();

    $notRemotePivot = ListingUser::query()->where('listing_id', $notRemote->id)->first();
    expect($notRemotePivot->relevance)->toBe(Relevance::Irrelevant)
        ->and($notRemotePivot->score_data['filter_reason'])->toBe('not_remote');

    $lowSalaryPivot = ListingUser::query()->where('listing_id', $lowSalary->id)->first();
    expect($lowSalaryPivot->relevance)->toBe(Relevance::Irrelevant)
        ->and($lowSalaryPivot->score_data['filter_reason'])->toBe('below_salary_min');
});

it('skips dispatch and emails admin when user has hit the monthly AI cap', function () {
    Queue::fake();
    Mail::fake();
    config(['scoring.monthly_cap_usd' => 1.0]);
    config(['scoring.admin_alert_email' => 'admin@example.com']);

    AiUsage::factory()->create([
        'user_id' => $this->user->id,
        'cost' => 5.0,
    ]);

    $listing = Listing::factory()->create(['remote' => true, 'salary_max' => null]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
    ]);

    $this->artisan('jobs:score')->assertSuccessful();

    Queue::assertNothingPushed();
    Mail::assertSent(UserCapReached::class);
});
