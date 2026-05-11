<?php

use App\Jobs\ScrapeBoard;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    login();
});

it('dispatches a scrape job for each enabled board', function () {
    Queue::fake();

    $expected = collect(config('boards'))->where('enabled', true)->count();

    $this->artisan('jobs:scrape')
        ->assertSuccessful();

    Queue::assertPushed(ScrapeBoard::class, $expected);
});

it('skips disabled boards', function () {
    Queue::fake();

    $expected = collect(config('boards'))->where('enabled', true)->count() - 1;
    config(['boards.larajobs.enabled' => false]);

    $this->artisan('jobs:scrape')
        ->assertSuccessful();

    Queue::assertPushed(ScrapeBoard::class, $expected);
});

it('outputs the number of dispatched jobs', function () {
    Queue::fake();

    $expected = collect(config('boards'))->where('enabled', true)->count();

    $this->artisan('jobs:scrape')
        ->expectsOutputToContain("Dispatched {$expected} scrape jobs");
});
