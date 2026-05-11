<?php

use App\Jobs\ScrapeBoard;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Services\Scrapers\ScraperInterface;
use Illuminate\Support\Facades\DB;

class StubScraper implements ScraperInterface
{
    /** @var array<int, array<string, mixed>> */
    public static array $rows = [];

    public function scrape(): Generator
    {
        foreach (self::$rows as $row) {
            yield $row;
        }
    }
}

function stubRow(array $overrides = []): array
{
    $defaults = [
        'title' => 'Senior Engineer',
        'company' => 'Acme Corp',
        'url' => 'https://example.com/job/'.fake()->unique()->uuid(),
        'description' => 'Build cool things.',
        'salary_min' => 150000,
        'salary_max' => 200000,
        'remote' => true,
        'raw_data' => ['source' => 'test'],
    ];

    $row = [...$defaults, ...$overrides];
    $row['source_url'] ??= $row['url'];

    return $row;
}

beforeEach(function () {
    StubScraper::$rows = [];
    // Migration `2026_04_04_000005_seed_existing_data_for_multi_user` creates an admin
    // user auto-subscribed to all boards. Wipe so each test sets up its own subscribers.
    DB::table('board_user')->delete();
});

it('inserts new listings via a small fixed number of queries regardless of row count', function () {
    StubScraper::$rows = collect(range(1, 50))
        ->map(fn (int $i) => stubRow(['url' => "https://example.com/job/{$i}"]))
        ->all();

    DB::enableQueryLog();
    (new ScrapeBoard('hn', StubScraper::class))->handle();
    $queries = DB::getQueryLog();

    expect(Listing::count())->toBe(50);

    // 1: pre-fetch existing URLs, 2: upsert, 3: board_user lookup. No pivot insert
    // because no subscribers. Crucially this is constant — not O(rows).
    expect(count($queries))->toBe(3);
});

it('updates an existing listing without changing its id and without creating new pivots', function () {
    $existing = Listing::factory()->create([
        'url' => 'https://example.com/existing',
        'title' => 'Old Title',
        'company' => 'Old Co',
    ]);
    $originalId = $existing->id;

    StubScraper::$rows = [
        stubRow(['url' => 'https://example.com/existing', 'title' => 'New Title', 'company' => 'New Co']),
    ];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    $existing->refresh();
    expect($existing->id)->toBe($originalId)
        ->and($existing->title)->toBe('New Title')
        ->and($existing->company)->toBe('New Co')
        ->and(Listing::count())->toBe(1)
        ->and(ListingUser::count())->toBe(0);
});

it('attaches pivots only for genuinely new listings to subscribed users', function () {
    $user = User::factory()->create();
    targetFor($user);
    DB::table('board_user')->insert([
        'user_id' => $user->id,
        'board_key' => 'hn',
        'created_at' => now(),
    ]);

    $existing = Listing::factory()->create(['url' => 'https://example.com/existing']);

    StubScraper::$rows = [
        stubRow(['url' => 'https://example.com/existing']),
        stubRow(['url' => 'https://example.com/new1']),
        stubRow(['url' => 'https://example.com/new2']),
    ];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    expect(Listing::count())->toBe(3)
        ->and(ListingUser::count())->toBe(2);

    $pivotedUrls = ListingUser::query()
        ->join('listings', 'listings.id', '=', 'listing_user.listing_id')
        ->pluck('listings.url')
        ->all();
    expect($pivotedUrls)->toEqualCanonicalizing(['https://example.com/new1', 'https://example.com/new2']);
});

it('creates one pivot per active target per new listing', function () {
    $user = User::factory()->create();
    targetFor($user, ['name' => 'EM']);
    targetFor($user, ['name' => 'IC']);
    targetFor($user, ['name' => 'Inactive', 'is_active' => false]);
    DB::table('board_user')->insert([
        'user_id' => $user->id,
        'board_key' => 'hn',
        'created_at' => now(),
    ]);

    StubScraper::$rows = [
        stubRow(['url' => 'https://example.com/multi-target-1']),
    ];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    // 1 listing × 2 active targets = 2 pivots
    expect(ListingUser::count())->toBe(2);
});

it('handles an empty scrape gracefully', function () {
    StubScraper::$rows = [];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    expect(Listing::count())->toBe(0)
        ->and(ListingUser::count())->toBe(0);
});

it('encodes raw_data so it round-trips through the array cast', function () {
    StubScraper::$rows = [
        stubRow([
            'url' => 'https://example.com/raw',
            'raw_data' => ['hn_id' => '12345', 'author' => 'someone', 'nested' => ['a' => 1]],
        ]),
    ];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    $listing = Listing::where('url', 'https://example.com/raw')->first();
    expect($listing->raw_data)->toBe(['hn_id' => '12345', 'author' => 'someone', 'nested' => ['a' => 1]]);
});

it('dedups by source_url even when apply url changes between scrapes', function () {
    StubScraper::$rows = [
        stubRow([
            'url' => 'https://news.ycombinator.com/item?id=99',
            'source_url' => 'https://news.ycombinator.com/item?id=99',
            'title' => 'First Pass',
        ]),
    ];
    (new ScrapeBoard('hn', StubScraper::class))->handle();
    $listingId = Listing::sole()->id;

    StubScraper::$rows = [
        stubRow([
            'url' => 'https://acme.com/apply',
            'source_url' => 'https://news.ycombinator.com/item?id=99',
            'title' => 'Second Pass',
        ]),
    ];
    (new ScrapeBoard('hn', StubScraper::class))->handle();

    $listing = Listing::sole();
    expect($listing->id)->toBe($listingId)
        ->and($listing->title)->toBe('Second Pass')
        ->and($listing->url)->toBe('https://acme.com/apply')
        ->and($listing->source_url)->toBe('https://news.ycombinator.com/item?id=99');
});
