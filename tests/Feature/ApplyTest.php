<?php

use App\ApplicationStatus;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Illuminate\Support\Facades\Bus;

it('creates an application and dispatches generation jobs', function () {
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

    $this->post(route('apply', $listing))
        ->assertRedirect(route('filament.admin.resources.listings.view', $listing));

    expect(Application::count())->toBe(1);

    $application = Application::first();
    expect($application->listing_id)->toBe($listing->id)
        ->and($application->user_id)->toBe($user->id)
        ->and($application->target_profile_id)->toBe($target->id)
        ->and($application->status)->toBe(ApplicationStatus::Generating);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume)
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('redirects with status message', function () {
    Bus::fake();

    $user = login();
    $target = targetFor($user, ['name' => 'EM roles']);
    $listing = Listing::factory()->create(['company' => 'Acme Corp']);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $this->post(route('apply', $listing))
        ->assertRedirect(route('filament.admin.resources.listings.view', $listing))
        ->assertSessionHas('status', 'Generating application for Acme Corp (EM roles)...');
});
