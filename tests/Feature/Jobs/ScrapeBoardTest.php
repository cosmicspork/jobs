<?php

use App\Jobs\EnrichListing;
use App\Jobs\ScrapeBoard;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Services\Scrapers\ScraperInterface;
use Illuminate\Support\Facades\Bus;
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

it('preserves created_by_user_id on a manually-created listing when re-scraped', function () {
    $author = User::factory()->create();
    $manual = Listing::factory()->create([
        'url' => 'https://example.com/manual-job',
        'title' => 'Manual Title',
        'company' => 'Manual Co',
        'board' => 'manual',
        'created_by_user_id' => $author->id,
    ]);

    StubScraper::$rows = [
        stubRow(['url' => 'https://example.com/manual-job', 'title' => 'Scraper Title', 'company' => 'Scraper Co']),
    ];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    $manual->refresh();
    expect($manual->created_by_user_id)->toBe($author->id)
        ->and($manual->title)->toBe('Scraper Title')
        ->and(Listing::count())->toBe(1)
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

it('marks listings from non-enrichment boards as inline-enriched immediately', function () {
    StubScraper::$rows = [stubRow(['url' => 'https://example.com/hn-job'])];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    $listing = Listing::sole();
    expect($listing->enrichment_source)->toBe('inline')
        ->and($listing->enriched_at)->not->toBeNull();
});

it('leaves enrichment-required listings unenriched and dispatches EnrichListing per new row', function () {
    Bus::fake([EnrichListing::class]);

    StubScraper::$rows = [
        stubRow(['url' => 'https://larajobs.com/job/1']),
        stubRow(['url' => 'https://larajobs.com/job/2']),
    ];

    (new ScrapeBoard('larajobs', StubScraper::class))->handle();

    expect(Listing::count())->toBe(2);
    foreach (Listing::all() as $listing) {
        expect($listing->enriched_at)->toBeNull()
            ->and($listing->enrichment_source)->toBeNull();
    }
    Bus::assertDispatchedTimes(EnrichListing::class, 2);
});

it('does not re-dispatch EnrichListing for already-existing listings on re-scrape', function () {
    Bus::fake([EnrichListing::class]);

    Listing::factory()->awaitingEnrichment()->create(['url' => 'https://larajobs.com/job/exists', 'source_url' => 'https://larajobs.com/job/exists']);

    StubScraper::$rows = [stubRow(['url' => 'https://larajobs.com/job/exists'])];

    (new ScrapeBoard('larajobs', StubScraper::class))->handle();

    Bus::assertNotDispatched(EnrichListing::class);
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
it('does not overwrite user-editable fields when a listing has been manually edited', function () {
    $listing = Listing::factory()->create([
        'url' => 'https://example.com/edited-job',
        'source_url' => 'https://example.com/edited-job',
        'title' => 'My Corrected Title',
        'company' => 'Corrected Co',
        'description' => 'Full description pasted by user.',
        'salary_min' => 180000,
        'salary_max' => 220000,
        'remote' => true,
        'manually_edited_at' => now(),
        'scraped_at' => now()->subDay(),
        'raw_data' => ['original' => true],
    ]);

    $originalScrapedAt = $listing->scraped_at;

    StubScraper::$rows = [
        stubRow([
            'url' => 'https://example.com/edited-job',
            'title' => 'Scraper Title',
            'company' => 'Scraper Co',
            'description' => 'Stub description from scraper.',
            'salary_min' => 100000,
            'salary_max' => 130000,
            'remote' => false,
            'raw_data' => ['updated' => true],
        ]),
    ];

    (new ScrapeBoard('hn', StubScraper::class))->handle();

    $listing->refresh();

    // User-editable fields must survive the re-scrape.
    expect($listing->title)->toBe('My Corrected Title')
        ->and($listing->company)->toBe('Corrected Co')
        ->and($listing->description)->toBe('Full description pasted by user.')
        ->and($listing->salary_min)->toBe(180000)
        ->and($listing->salary_max)->toBe(220000)
        ->and($listing->remote)->toBeTrue();

    // Scraper metadata must still flow through.
    expect($listing->raw_data)->toBe(['updated' => true])
        ->and($listing->scraped_at)->not->toEqual($originalScrapedAt);
});

it('does not inflate query count when all listings are unedited', function () {
    StubScraper::$rows = collect(range(1, 20))
        ->map(fn (int $i) => stubRow(['url' => "https://example.com/job/{$i}"]))
        ->all();

    // Seed a batch of existing unedited rows.
    (new ScrapeBoard('hn', StubScraper::class))->handle();

    DB::enableQueryLog();
    (new ScrapeBoard('hn', StubScraper::class))->handle();
    $queries = DB::getQueryLog();

    // 1: pre-fetch existing listings, 2: full upsert (no edited rows → metadata upsert
    // skipped). board_user lookup is omitted because $created = 0. Constant — not O(rows).
    expect(count($queries))->toBe(2);
});
