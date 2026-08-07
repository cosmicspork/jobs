<?php

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login();
    $this->target = targetFor($this->user);
});

function stagedListing(string $title, int $userId, string $targetId, array $flags = []): Listing
{
    $listing = Listing::factory()->create(['title' => $title]);

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $userId,
        'target_profile_id' => $targetId,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        ...$flags,
    ]);

    return $listing;
}

/** @return array<string, Listing> */
function oneListingPerStage(int $userId, string $targetId): array
{
    return [
        'inbox' => stagedListing('Inbox Job', $userId, $targetId),
        'starred' => stagedListing('Starred Job', $userId, $targetId, [
            'read_at' => now(), 'starred_at' => now(),
        ]),
        'shortlisted' => stagedListing('Shortlisted Job', $userId, $targetId, [
            'read_at' => now(), 'starred_at' => now(), 'shortlisted_at' => now(),
        ]),
        'applied' => stagedListing('Applied Job', $userId, $targetId, [
            'read_at' => now(), 'starred_at' => now(),
            'shortlisted_at' => now(), 'applied_at' => now(),
        ]),
    ];
}

it('shows each listing in exactly one stage', function (string $stage) {
    $listings = oneListingPerStage($this->user->id, $this->target->id);

    Livewire::test(ListListings::class, ['activeTab' => $stage])
        ->assertCanSeeTableRecords([$listings[$stage]])
        ->assertCanNotSeeTableRecords(
            collect($listings)->except($stage)->values()->all()
        );
})->with(['inbox', 'starred', 'shortlisted', 'applied']);

it('drains a listing out of the inbox once it is starred', function () {
    $listing = stagedListing('Fresh Job', $this->user->id, $this->target->id);

    Livewire::test(ListListings::class, ['activeTab' => 'inbox'])
        ->assertCanSeeTableRecords([$listing]);

    ListingUser::forUserListing($this->user->id, $listing->id)->star();

    Livewire::test(ListListings::class, ['activeTab' => 'inbox'])
        ->assertCanNotSeeTableRecords([$listing]);

    Livewire::test(ListListings::class, ['activeTab' => 'starred'])
        ->assertCanSeeTableRecords([$listing]);
});

it('keeps a shortlisted listing out of the starred stage', function () {
    $listing = stagedListing('Queued Job', $this->user->id, $this->target->id, [
        'read_at' => now(), 'starred_at' => now(), 'shortlisted_at' => now(),
    ]);

    Livewire::test(ListListings::class, ['activeTab' => 'starred'])
        ->assertCanNotSeeTableRecords([$listing]);

    Livewire::test(ListListings::class, ['activeTab' => 'shortlisted'])
        ->assertCanSeeTableRecords([$listing]);
});

it('still shows every stage under the All tab', function () {
    $listings = oneListingPerStage($this->user->id, $this->target->id);

    Livewire::test(ListListings::class, ['activeTab' => 'all'])
        ->assertCanSeeTableRecords(array_values($listings));
});

it('counts each listing against exactly one tab badge', function () {
    oneListingPerStage($this->user->id, $this->target->id);

    $tabs = Livewire::test(ListListings::class)->instance()->getTabs();

    // Filament stringifies badge values on the way out.
    expect($tabs['inbox']->getBadge())->toEqual(1)
        ->and($tabs['starred']->getBadge())->toEqual(1)
        ->and($tabs['shortlisted']->getBadge())->toEqual(1)
        ->and($tabs['applied']->getBadge())->toEqual(1);
});

it('excludes dismissed listings from every stage badge', function () {
    stagedListing('Dismissed Job', $this->user->id, $this->target->id, [
        'read_at' => now(), 'starred_at' => now(), 'dismissed_at' => now(),
    ]);

    $tabs = Livewire::test(ListListings::class)->instance()->getTabs();

    expect($tabs['starred']->getBadge())->toBeNull();
});
