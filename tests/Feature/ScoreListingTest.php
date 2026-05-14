<?php

use App\Jobs\ScoreListing;
use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->ic()->create();
    $this->target = $this->user->targetProfiles()->first();
    $this->listing = Listing::factory()->create();

    $this->pivot = ListingUser::create([
        'listing_id' => $this->listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);
});

it('returns silently without scoring when the user is over their AI cap', function () {
    config(['scoring.monthly_cap_usd' => 1.0]);
    AiUsage::factory()->create([
        'user_id' => $this->user->id,
        'cost' => 5.0,
    ]);

    (new ScoreListing($this->listing, $this->target))->handle();

    expect($this->pivot->fresh())
        ->scored_at->toBeNull()
        ->relevance->toBeNull();
});

it('honors a per-user cap below the global config', function () {
    config(['scoring.monthly_cap_usd' => 100.0]);
    $this->user->update(['monthly_ai_cap_usd' => 1.0]);
    AiUsage::factory()->create([
        'user_id' => $this->user->id,
        'cost' => 2.0,
    ]);

    (new ScoreListing($this->listing, $this->target))->handle();

    expect($this->pivot->fresh()->scored_at)->toBeNull();
});
