<?php

use App\Filament\Widgets\ListingStats;
use App\Filament\Widgets\RelevanceByBoardChart;
use App\Models\Listing;
use App\Relevance;
use Livewire\Livewire;

it('renders the dashboard page', function () {
    $this->get(route('filament.admin.pages.dashboard'))
        ->assertSuccessful();
});

it('displays listing stats', function () {
    Listing::factory(3)->scored(Relevance::Relevant)->create(['board' => 'larajobs']);
    Listing::factory(5)->scored(Relevance::Irrelevant)->create(['board' => 'hn']);
    Listing::factory(2)->create();

    Livewire::test(ListingStats::class)
        ->assertSee('Total Listings')
        ->assertSee('10')
        ->assertSee('Relevant')
        ->assertSee('3')
        ->assertSee('Unscored')
        ->assertSee('2');
});

it('shows relevance breakdown by board', function () {
    Listing::factory(3)->scored(Relevance::Relevant)->create(['board' => 'larajobs']);
    Listing::factory(2)->scored(Relevance::Irrelevant)->create(['board' => 'hn']);

    Livewire::test(RelevanceByBoardChart::class)
        ->assertSuccessful();
});
