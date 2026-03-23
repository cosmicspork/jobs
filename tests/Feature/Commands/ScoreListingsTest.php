<?php

use App\Jobs\ScoreListing;
use App\Models\Listing;
use Illuminate\Support\Facades\Queue;

it('dispatches scoring jobs for unscored listings', function () {
    Queue::fake();

    Listing::factory()->count(3)->create();

    $this->artisan('jobs:score')
        ->assertSuccessful();

    Queue::assertPushed(ScoreListing::class, 3);
});

it('skips already scored listings', function () {
    Queue::fake();

    Listing::factory()->scored()->create();
    Listing::factory()->create();

    $this->artisan('jobs:score')
        ->assertSuccessful();

    Queue::assertPushed(ScoreListing::class, 1);
});

it('reports when no unscored listings exist', function () {
    Queue::fake();

    $this->artisan('jobs:score')
        ->expectsOutputToContain('No unscored listings found');
});
