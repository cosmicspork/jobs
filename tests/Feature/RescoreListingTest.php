<?php

use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Jobs\ScoreListing;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

function rescoreAction(): TestAction
{
    return TestAction::make('rescore')->schemaComponent('match');
}

beforeEach(function () {
    Queue::fake();

    $this->user = login();
    $this->target = targetFor($this->user, ['name' => 'Primary']);
    $this->secondaryTarget = targetFor($this->user, ['name' => 'Secondary']);
    targetFor($this->user, ['name' => 'Inactive', 'is_active' => false]);
});

it('rescore action dispatches ScoreListing for each active target', function () {
    $listing = Listing::factory()->create();

    foreach ([$this->target, $this->secondaryTarget] as $target) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'target_profile_id' => $target->id,
            'relevance' => Relevance::Maybe,
            'scored_at' => now()->subDay(),
        ]);
    }

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(rescoreAction());

    Queue::assertPushed(ScoreListing::class, 2);
});

it('rescore action stamps digested_at on never-notified pivots', function () {
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subDay(),
        'digested_at' => null,
    ]);

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(rescoreAction());

    expect($pivot->refresh()->digested_at)->not->toBeNull();
});

it('rescore action does not move digested_at on already-notified pivots', function () {
    $listing = Listing::factory()->create();
    $earlier = now()->subDays(3)->startOfSecond();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subDay(),
        'digested_at' => $earlier,
    ]);

    $beforeTimestamp = $pivot->refresh()->digested_at?->getTimestamp();

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(rescoreAction());

    expect($pivot->refresh()->digested_at?->getTimestamp())->toBe($beforeTimestamp);
});

it('rescore action skips pivots tied to inactive targets', function () {
    $listing = Listing::factory()->create();
    $inactiveTarget = $this->user->targetProfiles()->where('is_active', false)->first();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $inactiveTarget->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subDay(),
    ]);

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(rescoreAction());

    Queue::assertNotPushed(ScoreListing::class);
});

it('hides rescore when every active target already has an up-to-date score', function () {
    $listing = Listing::factory()->create();

    foreach ([$this->target, $this->secondaryTarget] as $target) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'target_profile_id' => $target->id,
            'relevance' => Relevance::Maybe,
            'scored_at' => now(),
        ]);
    }

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->assertActionDoesNotExist(rescoreAction());
});

it('shows rescore when an active target has no pivot for this listing', function () {
    $listing = Listing::factory()->create();

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Maybe,
        'scored_at' => now(),
    ]);

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->assertActionVisible(rescoreAction());
});

it('shows rescore when a pivot has no scored_at yet', function () {
    $listing = Listing::factory()->create();

    foreach ([$this->target, $this->secondaryTarget] as $target) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'target_profile_id' => $target->id,
            'scored_at' => null,
        ]);
    }

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->assertActionVisible(rescoreAction());
});

it('shows rescore when a target was edited after its last score', function () {
    $listing = Listing::factory()->create();

    foreach ([$this->target, $this->secondaryTarget] as $target) {
        ListingUser::create([
            'listing_id' => $listing->id,
            'user_id' => $this->user->id,
            'target_profile_id' => $target->id,
            'relevance' => Relevance::Maybe,
            'scored_at' => now()->subDay(),
        ]);
    }

    $this->target->update(['positioning' => 'Edited after the score']);

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->assertActionVisible(rescoreAction());
});
