<?php

use App\Filament\Pages\ReadingRoom;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login();
    $this->target = targetFor($this->user);
});

function railListing(string $title, int $userId, string $targetId, array $flags = [], ?int $scoredMinutesAgo = null): Listing
{
    $listing = Listing::factory()->create(['title' => $title]);

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $userId,
        'target_profile_id' => $targetId,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subMinutes($scoredMinutesAgo ?? 0),
        ...$flags,
    ]);

    return $listing;
}

it('renders the reading room', function () {
    railListing('Readable Job', $this->user->id, $this->target->id);

    $this->get(ReadingRoom::getUrl())
        ->assertSuccessful()
        ->assertSee('Readable Job');
});

it('selects the first listing in the stage on mount', function () {
    $first = railListing('First Job', $this->user->id, $this->target->id, [], 0);
    railListing('Second Job', $this->user->id, $this->target->id, [], 10);

    Livewire::test(ReadingRoom::class)
        ->assertSet('selectedId', $first->id);
});

it('advances to the successor when the acted-on listing drains', function () {
    $first = railListing('First Job', $this->user->id, $this->target->id, [], 0);
    $second = railListing('Second Job', $this->user->id, $this->target->id, [], 10);

    Livewire::test(ReadingRoom::class)
        ->assertSet('selectedId', $first->id)
        ->call('act', 'star')
        ->assertSet('selectedId', $second->id);

    expect(ListingUser::forUserListing($this->user->id, $first->id)->starred_at)->not->toBeNull();
});

it('falls back to the previous listing when acting on the last one', function () {
    $first = railListing('First Job', $this->user->id, $this->target->id, [], 0);
    $second = railListing('Second Job', $this->user->id, $this->target->id, [], 10);

    Livewire::test(ReadingRoom::class)
        ->set('selectedId', $second->id)
        ->call('act', 'dismiss')
        ->assertSet('selectedId', $first->id);
});

it('clears the selection when the last listing in a stage drains', function () {
    railListing('Only Job', $this->user->id, $this->target->id);

    Livewire::test(ReadingRoom::class)
        ->call('act', 'dismiss')
        ->assertSet('selectedId', null);
});

it('keeps the selection when acting does not drain the listing', function () {
    $listing = railListing('Applied Job', $this->user->id, $this->target->id, [
        'read_at' => now(), 'starred_at' => now(),
        'shortlisted_at' => now(), 'applied_at' => now(),
    ]);

    Livewire::test(ReadingRoom::class)
        ->set('stage', 'applied')
        ->set('selectedId', $listing->id)
        ->call('act', 'star')
        ->assertSet('selectedId', $listing->id);
});

it('moves the selection with j and k offsets', function () {
    $first = railListing('First Job', $this->user->id, $this->target->id, [], 0);
    $second = railListing('Second Job', $this->user->id, $this->target->id, [], 10);

    Livewire::test(ReadingRoom::class)
        ->call('move', 1)
        ->assertSet('selectedId', $second->id)
        ->call('move', -1)
        ->assertSet('selectedId', $first->id)
        ->call('move', -1)
        ->assertSet('selectedId', $first->id);
});

it('switches stage and reselects', function () {
    railListing('Inbox Job', $this->user->id, $this->target->id);
    $starred = railListing('Starred Job', $this->user->id, $this->target->id, [
        'read_at' => now(), 'starred_at' => now(),
    ]);

    Livewire::test(ReadingRoom::class)
        ->call('selectStage', 'starred')
        ->assertSet('stage', 'starred')
        ->assertSet('selectedId', $starred->id)
        ->assertSee('Starred Job')
        ->assertDontSee('Inbox Job');
});

it('does not mark a listing read merely by selecting it', function () {
    $listing = railListing('Unread Job', $this->user->id, $this->target->id);

    Livewire::test(ReadingRoom::class)
        ->call('select', $listing->id);

    expect(ListingUser::forUserListing($this->user->id, $listing->id)->read_at)->toBeNull();
});

it('marks the selected listing read on the dwell callback', function () {
    $listing = railListing('Dwelt Job', $this->user->id, $this->target->id);

    Livewire::test(ReadingRoom::class)
        ->call('select', $listing->id)
        ->call('markSelectedRead');

    expect(ListingUser::forUserListing($this->user->id, $listing->id)->read_at)->not->toBeNull();
});

it('strips embedded html from scraped descriptions', function () {
    $listing = Listing::factory()->create([
        'title' => 'Injected Job',
        'description' => "Real copy.\n\n<script>alert('xss')</script>\n\n<img src=x onerror=alert(1)>",
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);

    $html = Livewire::test(ReadingRoom::class)
        ->call('select', $listing->id)
        ->instance()
        ->descriptionHtml;

    expect($html)->toContain('Real copy.')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('onerror');
});

it('does not leak another user\'s listings into the rail', function () {
    $mine = railListing('My Job', $this->user->id, $this->target->id);

    $other = User::factory()->create();
    railListing('Their Job', $other->id, targetFor($other)->id);

    Livewire::test(ReadingRoom::class)
        ->assertSee('My Job')
        ->assertDontSee('Their Job')
        ->assertSet('selectedId', $mine->id);
});

it('does not select a listing belonging to another user', function () {
    railListing('My Job', $this->user->id, $this->target->id);

    $other = User::factory()->create();
    $theirs = railListing('Their Job', $other->id, targetFor($other)->id);

    $page = Livewire::test(ReadingRoom::class)->call('select', $theirs->id);

    expect($page->instance()->selected)->toBeNull();
});
