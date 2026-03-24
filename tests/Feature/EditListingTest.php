<?php

use App\Filament\Resources\Listings\Pages\EditListing;
use App\Models\Listing;
use App\Relevance;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

it('can load the edit page', function () {
    $listing = Listing::factory()->scored()->create();

    Livewire::test(EditListing::class, ['record' => $listing->id])
        ->assertOk();
});

it('can update listing fields', function () {
    $listing = Listing::factory()->scored()->create();

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

it('can override relevance scoring', function () {
    $listing = Listing::factory()->scored(Relevance::Maybe)->create();

    Livewire::test(EditListing::class, ['record' => $listing->id])
        ->fillForm([
            'relevance' => Relevance::Relevant->value,
        ])
        ->call('save')
        ->assertNotified();

    $listing->refresh();
    expect($listing->relevance)->toBe(Relevance::Relevant);
});
