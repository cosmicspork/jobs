<?php

use App\Jobs\ScrapeBoard;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    login();
});

it('dispatches a scrape job for each enabled board', function () {
    Queue::fake();

    $this->artisan('jobs:scrape')
        ->assertSuccessful();

    Queue::assertPushed(ScrapeBoard::class, 2);
});

it('skips disabled boards', function () {
    Queue::fake();

    config(['boards.larajobs.enabled' => false]);

    $this->artisan('jobs:scrape')
        ->assertSuccessful();

    Queue::assertPushed(ScrapeBoard::class, 1);
});

it('outputs the number of dispatched jobs', function () {
    Queue::fake();

    $this->artisan('jobs:scrape')
        ->expectsOutputToContain('Dispatched 2 scrape jobs');
});
