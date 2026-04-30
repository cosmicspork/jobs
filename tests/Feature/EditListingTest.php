<?php

use App\Filament\Resources\Listings\Pages\EditListing;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    $this->user = login();
});

it('can load the edit page', function () {
    $listing = Listing::factory()->create();

    Livewire::test(EditListing::class, ['record' => $listing->id])
        ->assertOk();
});

it('can update listing fields', function () {
    $listing = Listing::factory()->create();

    Livewire::test(EditListing::class, ['record' => $listing->id])
        ->fillForm([
            'title' => 'Updated Title',
            'company' => 'Updated Company',
            'description' => 'Full job posting description pasted here.',
            'salary_min' => 130000,
            'salary_max' => 180000,
            'remote' => true,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Listing::class, [
        'id' => $listing->id,
        'title' => 'Updated Title',
        'company' => 'Updated Company',
        'salary_min' => 130000,
        'salary_max' => 180000,
    ]);
});

it('can update relevance on the pivot directly', function () {
    $target = targetFor($this->user);
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Maybe,
        'scored_at' => now(),
    ]);

    $pivot->update(['relevance' => Relevance::Relevant]);
    $pivot->refresh();

    expect($pivot->relevance)->toBe(Relevance::Relevant);
});
