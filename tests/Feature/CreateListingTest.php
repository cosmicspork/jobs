<?php

use App\Filament\Resources\Listings\Pages\CreateListing;
use App\Jobs\ScoreListing;
use App\Models\Listing;
use App\Models\ListingUser;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = login();
    Queue::fake();
});

it('can load the create page', function () {
    Livewire::test(CreateListing::class)
        ->assertOk();
});

it('creates a listing with sane defaults and a pivot for each active target', function () {
    $primary = targetFor($this->user, ['name' => 'Primary', 'is_active' => true]);
    $secondary = targetFor($this->user, ['name' => 'Secondary', 'is_active' => true]);
    targetFor($this->user, ['name' => 'Inactive', 'is_active' => false]);

    Livewire::test(CreateListing::class)
        ->fillForm([
            'title' => 'Senior Engineer',
            'company' => 'Acme',
            'url' => 'https://example.com/jobs/123',
            'description' => 'Pasted job posting body.',
            'remote' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(Listing::class, [
        'title' => 'Senior Engineer',
        'company' => 'Acme',
        'url' => 'https://example.com/jobs/123',
        'board' => 'manual',
        'remote' => true,
    ]);

    $listing = Listing::where('title', 'Senior Engineer')->firstOrFail();

    expect($listing->scraped_at)->not->toBeNull();

    $pivots = ListingUser::where('listing_id', $listing->id)
        ->where('user_id', $this->user->id)
        ->get();

    expect($pivots)->toHaveCount(2);
    expect($pivots->pluck('target_profile_id')->sort()->values()->all())
        ->toEqual(collect([$primary->id, $secondary->id])->sort()->values()->all());

    Queue::assertPushed(ScoreListing::class, 2);
    Queue::assertPushed(ScoreListing::class, fn (ScoreListing $job) => $job->listing->is($listing) && $job->target->is($primary));
    Queue::assertPushed(ScoreListing::class, fn (ScoreListing $job) => $job->listing->is($listing) && $job->target->is($secondary));
});

it('halts and warns when the user has no active targets', function () {
    Livewire::test(CreateListing::class)
        ->fillForm([
            'title' => 'Junior Dev',
            'company' => 'Solo Co',
            'url' => 'https://example.com/jobs/junior-dev',
            'description' => 'Posting body.',
        ])
        ->call('create')
        ->assertNotified('No active target');

    expect(Listing::where('title', 'Junior Dev')->exists())->toBeFalse();

    Queue::assertNotPushed(ScoreListing::class);
});

it('requires title, company, url, and description', function () {
    Livewire::test(CreateListing::class)
        ->fillForm([
            'title' => null,
            'company' => null,
            'url' => null,
            'description' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'title' => 'required',
            'company' => 'required',
            'url' => 'required',
            'description' => 'required',
        ]);
});
