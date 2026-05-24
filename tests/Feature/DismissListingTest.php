<?php

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Listings\Pages\ViewListing;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login();
    $this->target = targetFor($this->user);
});

function makePivot(string $listingId, int $userId, string $targetId, array $overrides = []): ListingUser
{
    return ListingUser::create([
        'listing_id' => $listingId,
        'user_id' => $userId,
        'target_profile_id' => $targetId,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        ...$overrides,
    ]);
}

it('hides dismissed listings from the default listings table', function () {
    $live = Listing::factory()->create(['title' => 'Live Job']);
    makePivot($live->id, $this->user->id, $this->target->id);

    $dismissed = Listing::factory()->create(['title' => 'Dismissed Job']);
    makePivot($dismissed->id, $this->user->id, $this->target->id, [
        'dismissed_at' => now(),
    ]);

    Livewire::test(ListListings::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$dismissed]);
});

it('surfaces dismissed listings when the dismissed filter is enabled', function () {
    $live = Listing::factory()->create();
    makePivot($live->id, $this->user->id, $this->target->id);

    $dismissed = Listing::factory()->create();
    makePivot($dismissed->id, $this->user->id, $this->target->id, [
        'dismissed_at' => now(),
    ]);

    Livewire::test(ListListings::class, ['activeTab' => 'all'])
        ->filterTable('dismissed', true)
        ->assertCanSeeTableRecords([$dismissed])
        ->assertCanNotSeeTableRecords([$live]);
});

it('shows both live and dismissed listings when the dismissed filter includes them', function () {
    $live = Listing::factory()->create();
    makePivot($live->id, $this->user->id, $this->target->id);

    $dismissed = Listing::factory()->create();
    makePivot($dismissed->id, $this->user->id, $this->target->id, [
        'dismissed_at' => now(),
    ]);

    Livewire::test(ListListings::class, ['activeTab' => 'all'])
        ->filterTable('dismissed', false)
        ->assertCanSeeTableRecords([$live, $dismissed]);
});

it('reports no active filters on a fresh listings table', function () {
    $listing = Listing::factory()->create();
    makePivot($listing->id, $this->user->id, $this->target->id);

    $table = Livewire::test(ListListings::class, ['activeTab' => 'all'])
        ->instance()
        ->getTable();

    expect($table->getActiveFiltersCount())->toBe(0);
});

it('toggles dismissed via the ViewListing page action', function () {
    $listing = Listing::factory()->create();
    $pivot = makePivot($listing->id, $this->user->id, $this->target->id);

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(TestAction::make('toggleDismissed')->schemaComponent('jobDetails'));

    expect($pivot->fresh()->dismissed_at)->not->toBeNull();

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(TestAction::make('toggleDismissed')->schemaComponent('jobDetails'));

    expect($pivot->fresh()->dismissed_at)->toBeNull();
});

it('dismisses selected listings only for the acting user', function () {
    $other = User::factory()->create();
    $otherTarget = targetFor($other);

    $listing = Listing::factory()->create();
    $mine = makePivot($listing->id, $this->user->id, $this->target->id);
    $theirs = makePivot($listing->id, $other->id, $otherTarget->id);

    Livewire::test(ListListings::class, ['activeTab' => 'all'])
        ->selectTableRecords([$listing->id])
        ->callAction(TestAction::make('dismiss')->table()->bulk());

    expect($mine->fresh()->dismissed_at)->not->toBeNull()
        ->and($theirs->fresh()->dismissed_at)->toBeNull();
});

it('toggles shortlisted via the ViewListing page action', function () {
    $listing = Listing::factory()->create();
    $pivot = makePivot($listing->id, $this->user->id, $this->target->id);

    expect($pivot->shortlisted_at)->toBeNull();

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(TestAction::make('toggleShortlisted')->schemaComponent('jobDetails'));

    expect($pivot->fresh()->shortlisted_at)->not->toBeNull();

    Livewire::test(ViewListing::class, ['record' => $listing->id])
        ->callAction(TestAction::make('toggleShortlisted')->schemaComponent('jobDetails'));

    expect($pivot->fresh()->shortlisted_at)->toBeNull();
});
