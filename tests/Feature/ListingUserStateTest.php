<?php

use App\ApplicationOutcome;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->user = login();

    // Two active targets, so every listing has two pivot rows. User-state flags
    // are per (listing, user) and must fan out across both or the "best pivot"
    // the UI reads from can disagree with the one that was written.
    $this->targetA = targetFor($this->user, ['name' => 'Target A']);
    $this->targetB = targetFor($this->user, ['name' => 'Target B']);

    $this->listing = Listing::factory()->create();

    foreach ([$this->targetA, $this->targetB] as $target) {
        ListingUser::create([
            'listing_id' => $this->listing->id,
            'user_id' => $this->user->id,
            'target_profile_id' => $target->id,
            'relevance' => Relevance::Relevant,
            'scored_at' => now(),
        ]);
    }
});

/** @return Collection<int, ListingUser> */
function pivotsFor(string $listingId, int $userId): Collection
{
    return ListingUser::where('listing_id', $listingId)
        ->where('user_id', $userId)
        ->get();
}

it('stamps applied_at across every target pivot', function () {
    ListingUser::forUserListing($this->user->id, $this->listing->id)->toggleApplied();

    expect(pivotsFor($this->listing->id, $this->user->id))->toHaveCount(2);
    expect(pivotsFor($this->listing->id, $this->user->id)->pluck('applied_at')->filter())->toHaveCount(2);
});

it('clears the outcome when un-applying', function () {
    $pivot = ListingUser::forUserListing($this->user->id, $this->listing->id);
    $pivot->toggleApplied();
    $pivot->refresh()->setOutcome(ApplicationOutcome::Interviewing);

    expect(pivotsFor($this->listing->id, $this->user->id)->pluck('outcome')->filter())->toHaveCount(2);

    ListingUser::forUserListing($this->user->id, $this->listing->id)->toggleApplied();

    expect(pivotsFor($this->listing->id, $this->user->id)->pluck('applied_at')->filter())->toBeEmpty()
        ->and(pivotsFor($this->listing->id, $this->user->id)->pluck('outcome')->filter())->toBeEmpty()
        ->and(pivotsFor($this->listing->id, $this->user->id)->pluck('outcome_at')->filter())->toBeEmpty();
});

it('records an outcome with its timestamp on every pivot', function () {
    ListingUser::forUserListing($this->user->id, $this->listing->id)
        ->setOutcome(ApplicationOutcome::Offer);

    expect(pivotsFor($this->listing->id, $this->user->id))->toHaveCount(2);

    pivotsFor($this->listing->id, $this->user->id)->each(function (ListingUser $pivot) {
        expect($pivot->outcome)->toBe(ApplicationOutcome::Offer)
            ->and($pivot->outcome_at)->not->toBeNull();
    });
});

it('marks a listing read when starring so it leaves the inbox', function () {
    ListingUser::forUserListing($this->user->id, $this->listing->id)->star();

    pivotsFor($this->listing->id, $this->user->id)->each(function (ListingUser $pivot) {
        expect($pivot->starred_at)->not->toBeNull()
            ->and($pivot->read_at)->not->toBeNull();
    });
});

it('preserves the original read time when starring an already-read listing', function () {
    $readAt = now()->subDays(3);
    ListingUser::where('listing_id', $this->listing->id)->update(['read_at' => $readAt]);

    ListingUser::forUserListing($this->user->id, $this->listing->id)->star();

    pivotsFor($this->listing->id, $this->user->id)->each(function (ListingUser $pivot) use ($readAt) {
        expect($pivot->read_at->timestamp)->toBe($readAt->timestamp);
    });
});

it('marks a listing read when shortlisting but does not star it', function () {
    ListingUser::forUserListing($this->user->id, $this->listing->id)->shortlist();

    pivotsFor($this->listing->id, $this->user->id)->each(function (ListingUser $pivot) {
        expect($pivot->shortlisted_at)->not->toBeNull()
            ->and($pivot->read_at)->not->toBeNull()
            ->and($pivot->starred_at)->toBeNull();
    });
});
