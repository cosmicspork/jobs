<?php

use App\Filament\Widgets\AdminOverviewStats;
use App\Filament\Widgets\PipelineHealth;
use App\Filament\Widgets\RelevanceByBoardBars;
use App\Filament\Widgets\ScrapeHealth;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function statColor(Stat $stat): ?string
{
    return (new ReflectionProperty($stat, 'color'))->getValue($stat);
}

function statDescription(Stat $stat): ?string
{
    $value = (new ReflectionProperty($stat, 'description'))->getValue($stat);

    return $value === null ? null : (string) $value;
}

/** @return array<int, Stat> */
function callGetStats(string $widgetClass): array
{
    $reflection = new ReflectionMethod($widgetClass, 'getStats');
    $reflection->setAccessible(true);

    return $reflection->invoke(new $widgetClass);
}

/** Insert a listing_user pivot bypassing Eloquent so created_at/scored_at are honored exactly. */
function insertPivot(string $listingId, int $userId, array $overrides = []): void
{
    DB::table('listing_user')->insert([
        'id' => (string) Str::ulid(),
        'listing_id' => $listingId,
        'user_id' => $userId,
        'relevance' => null,
        'score_data' => null,
        'scored_at' => null,
        'read_at' => null,
        'starred_at' => null,
        'shortlisted_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

beforeEach(function () {
    $this->admin = User::factory()->ic()->create(['is_admin' => true]);
    login($this->admin);
});

it('blocks non-admins from the admin dashboard', function () {
    login(User::factory()->ic()->create(['is_admin' => false]));

    $this->get(route('filament.admin.pages.admin-dashboard'))
        ->assertForbidden();
});

it('admins can load the admin dashboard', function () {
    $this->get(route('filament.admin.pages.admin-dashboard'))
        ->assertOk();
});

it('counts new listings by created_at, not scraped_at', function () {
    // A genuinely new listing today.
    Listing::factory()->create([
        'created_at' => now(),
        'scraped_at' => now(),
    ]);

    // A pre-existing listing the scraper just touched again. created_at is old,
    // scraped_at is now. Before the fix this counted as "today".
    Listing::factory()->create([
        'created_at' => now()->subDays(10),
        'scraped_at' => now(),
    ]);

    $stats = callGetStats(AdminOverviewStats::class);

    expect($stats[1]->getValue())->toBe(number_format(2))
        ->and(statDescription($stats[1]))->toContain('+1 today')
        ->and(statDescription($stats[1]))->toContain('+1 this week');
});

it('flags a board as stale when its last scrape is more than two hours ago', function () {
    Listing::factory()->create([
        'board' => 'hn',
        'scraped_at' => now()->subHours(3),
    ]);
    Listing::factory()->create([
        'board' => 'larajobs',
        'scraped_at' => now()->subMinutes(10),
    ]);

    [$hn, $larajobs] = callGetStats(ScrapeHealth::class);

    expect(statColor($hn))->toBe('danger')
        ->and(statColor($larajobs))->toBe('success');
});

it('flags pipeline health when scoring is dead and pivots are stuck', function () {
    // No ListingUser pivots scored. Add an unscored one that's two hours old —
    // bypass Eloquent so created_at is honored (it's not in $fillable).
    $listing = Listing::factory()->create();
    insertPivot($listing->id, $this->admin->id, [
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subHours(2),
    ]);

    $stats = callGetStats(PipelineHealth::class);

    expect($stats)->toHaveCount(4)
        ->and($stats[0]->getValue())->toBe('1')
        ->and(statColor($stats[0]))->toBe('danger')   // unscored stale
        ->and($stats[1]->getValue())->toBe('never')
        ->and(statColor($stats[1]))->toBe('danger');  // last successful score stale
});

it('shows pipeline health green when scoring is healthy', function () {
    $listing = Listing::factory()->create();
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->admin->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subMinutes(30),
    ]);

    $stats = callGetStats(PipelineHealth::class);

    expect($stats[0]->getValue())->toBe('0')
        ->and(statColor($stats[0]))->toBe('success')
        ->and(statColor($stats[1]))->toBe('success');
});

it('windows relevance-by-board bars to the last 30 days', function () {
    $hn = Listing::factory()->create(['board' => 'hn']);
    $larajobs = Listing::factory()->create(['board' => 'larajobs']);

    // Recent scoring: 1 relevant on hn, 1 irrelevant on larajobs.
    ListingUser::create([
        'listing_id' => $hn->id,
        'user_id' => $this->admin->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now()->subDays(2),
    ]);
    ListingUser::create([
        'listing_id' => $larajobs->id,
        'user_id' => $this->admin->id,
        'relevance' => Relevance::Irrelevant,
        'scored_at' => now()->subDays(2),
    ]);

    // Ancient scoring outside the 30-day window — should be excluded.
    $oldHn = Listing::factory()->create(['board' => 'hn']);
    insertPivot($oldHn->id, $this->admin->id, [
        'relevance' => Relevance::Irrelevant->value,
        'scored_at' => now()->subDays(60),
        'created_at' => now()->subDays(60),
        'updated_at' => now()->subDays(60),
    ]);

    $stats = callGetStats(RelevanceByBoardBars::class);

    expect($stats)->toHaveCount(2)
        ->and($stats[0]->getValue())->toBe('100% relevant')
        ->and($stats[1]->getValue())->toBe('0% relevant');
});
