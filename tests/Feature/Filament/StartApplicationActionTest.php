<?php

use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login();
    $this->target = targetFor($this->user, ['name' => 'Senior PHP']);
    $this->listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $this->listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);
});

it('creates the application, dispatches both jobs, and redirects to the workspace by default', function () {
    Bus::fake();

    Livewire::test(ViewListing::class, ['record' => $this->listing->getRouteKey()])
        ->callAction(TestAction::make('startApplication')->schemaComponent('application'), data: [
            'target_profile_id' => $this->target->id,
            'artifacts' => ['resume', 'cover_letter'],
            'extra_instructions' => 'Tone less formal.',
        ])
        ->assertNotified()
        ->assertRedirect(route('filament.admin.resources.applications.edit', Application::sole()));

    $application = Application::sole();
    expect($application->extra_instructions)->toBe('Tone less formal.')
        ->and($application->user_id)->toBe($this->user->id);

    Bus::assertBatched(function ($batch) {
        return $batch->jobs->count() === 2
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateResume)
            && $batch->jobs->contains(fn ($job) => $job instanceof GenerateCoverLetter);
    });
});

it('creates an empty workspace when no artifacts are selected', function () {
    Bus::fake();

    Livewire::test(ViewListing::class, ['record' => $this->listing->getRouteKey()])
        ->callAction(TestAction::make('startApplication')->schemaComponent('application'), data: [
            'target_profile_id' => $this->target->id,
            'artifacts' => [],
            'extra_instructions' => '',
        ])
        ->assertNotified();

    $application = Application::sole();
    expect($application->status->value)->toBe('ready')
        ->and($application->extra_instructions)->toBeNull();

    Bus::assertNothingBatched();
});

it('dispatches resume only when resume is the sole selected artifact', function () {
    Bus::fake();

    Livewire::test(ViewListing::class, ['record' => $this->listing->getRouteKey()])
        ->callAction(TestAction::make('startApplication')->schemaComponent('application'), data: [
            'target_profile_id' => $this->target->id,
            'artifacts' => ['resume'],
            'extra_instructions' => '',
        ])
        ->assertNotified();

    expect(Application::count())->toBe(1);
    Bus::assertBatchCount(1);
});
