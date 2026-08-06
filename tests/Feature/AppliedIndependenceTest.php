<?php

use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Relevance;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = login();
    $this->target = targetFor($this->user);
    $this->listing = Listing::factory()->create(['title' => 'Independent Job']);

    $this->pivot = ListingUser::create([
        'listing_id' => $this->listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
    ]);
});

it('does not treat an AI draft as having applied', function () {
    Application::factory()->for($this->listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);

    Livewire::test(ListListings::class, ['activeTab' => 'applied'])
        ->assertCanNotSeeTableRecords([$this->listing]);

    Livewire::test(ListListings::class, ['activeTab' => 'inbox'])
        ->assertCanSeeTableRecords([$this->listing]);
});

it('marks a listing applied without creating an application record', function () {
    $this->pivot->toggleApplied();

    Livewire::test(ListListings::class, ['activeTab' => 'applied'])
        ->assertCanSeeTableRecords([$this->listing]);

    expect(Application::where('listing_id', $this->listing->id)->count())->toBe(0)
        ->and($this->pivot->refresh()->applied_at)->not->toBeNull();
});

it('keeps a shortlisted listing on the shortlist when an AI draft exists', function () {
    $this->pivot->update(['read_at' => now(), 'shortlisted_at' => now()]);

    Application::factory()->for($this->listing)->create([
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);

    Livewire::test(ListListings::class, ['activeTab' => 'shortlisted'])
        ->assertCanSeeTableRecords([$this->listing]);
});

it('moves a listing off the shortlist only once it is applied to', function () {
    $this->pivot->update(['read_at' => now(), 'shortlisted_at' => now()]);

    Livewire::test(ListListings::class, ['activeTab' => 'shortlisted'])
        ->assertCanSeeTableRecords([$this->listing]);

    $this->pivot->refresh()->toggleApplied();

    Livewire::test(ListListings::class, ['activeTab' => 'shortlisted'])
        ->assertCanNotSeeTableRecords([$this->listing]);
});

it('returns a listing to the shortlist when applied is undone', function () {
    $this->pivot->update([
        'read_at' => now(), 'shortlisted_at' => now(), 'applied_at' => now(),
    ]);

    $this->pivot->refresh()->toggleApplied();

    Livewire::test(ListListings::class, ['activeTab' => 'shortlisted'])
        ->assertCanSeeTableRecords([$this->listing]);
});
