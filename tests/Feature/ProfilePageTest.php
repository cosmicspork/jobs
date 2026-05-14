<?php

use App\Filament\Pages\Profile;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Livewire\Livewire;

it('disables the digest toggle for users with incomplete profiles', function () {
    login(User::factory()->create());

    $this->get(route('filament.admin.pages.profile'))
        ->assertSee('Finish your profile (summary, skills, and at least one active target with positioning, target titles, and a remote preference) before enabling');
});

it('does not show the digest gate hint for users with complete profiles', function () {
    login(User::factory()->ic()->create());

    $this->get(route('filament.admin.pages.profile'))
        ->assertDontSee('Finish your profile (summary, skills, and at least one active target with positioning, target titles, and a remote preference) before enabling');
});

it('save preserves existing target ids and listing_user pivots', function () {
    $user = login(User::factory()->ic()->create());
    $target = $user->targetProfiles()->first();
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        'starred_at' => now(),
    ]);

    Livewire::test(Profile::class)
        ->call('save')
        ->assertNotified();

    expect($user->targetProfiles()->count())->toBe(1)
        ->and($user->targetProfiles()->first()->id)->toBe($target->id)
        ->and(ListingUser::find($pivot->id))->not->toBeNull()
        ->and(ListingUser::find($pivot->id)->starred_at)->not->toBeNull();
});
