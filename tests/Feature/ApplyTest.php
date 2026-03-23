<?php

use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use App\Models\Listing;
use Illuminate\Support\Facades\Bus;

it('creates an application and dispatches generation jobs', function () {
    Bus::fake();

    $listing = Listing::factory()->scored()->create();

    $this->post(route('apply', $listing))
        ->assertRedirect(route('filament.admin.resources.listings.view', $listing));

    expect(Application::count())->toBe(1);

    $application = Application::first();
    expect($application->listing_id)->toBe($listing->id)
        ->and($application->status)->toBe('generating');

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume)
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('redirects with status message', function () {
    Bus::fake();

    $listing = Listing::factory()->scored()->create(['company' => 'Acme Corp']);

    $this->post(route('apply', $listing))
        ->assertRedirect(route('filament.admin.resources.listings.view', $listing))
        ->assertSessionHas('status', 'Generating application for Acme Corp...');
});
