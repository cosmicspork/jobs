<?php

use App\Filament\Widgets\ListingStats;
use App\Filament\Widgets\RelevanceByBoardChart;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login();
});

it('renders the dashboard page', function () {
    $this->get(route('filament.admin.pages.dashboard'))
        ->assertSuccessful();
});

it('displays listing stats', function () {
    $relevantListings = Listing::factory(3)->create(['board' => 'larajobs']);
    foreach ($relevantListings as $listing) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'relevance' => Relevance::Relevant,
            'scored_at' => now(),
        ]);
    }

    $irrelevantListings = Listing::factory(5)->create(['board' => 'hn']);
    foreach ($irrelevantListings as $listing) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'relevance' => Relevance::Irrelevant,
            'scored_at' => now(),
        ]);
    }

    $unscoredListings = Listing::factory(2)->create();
    foreach ($unscoredListings as $listing) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
        ]);
    }

    Livewire::test(ListingStats::class)
        ->assertSee('Total Listings')
        ->assertSee('10')
        ->assertSee('Relevant')
        ->assertSee('3')
        ->assertSee('Unscored')
        ->assertSee('2');
});

it('shows relevance breakdown by board', function () {
    $relevantListings = Listing::factory(3)->create(['board' => 'larajobs']);
    foreach ($relevantListings as $listing) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'relevance' => Relevance::Relevant,
            'scored_at' => now(),
        ]);
    }

    $irrelevantListings = Listing::factory(2)->create(['board' => 'hn']);
    foreach ($irrelevantListings as $listing) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'relevance' => Relevance::Irrelevant,
            'scored_at' => now(),
        ]);
    }

    Livewire::test(RelevanceByBoardChart::class)
        ->assertSuccessful();
});
