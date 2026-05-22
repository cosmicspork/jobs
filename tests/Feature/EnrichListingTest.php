<?php

use App\Jobs\EnrichListing;
use App\Jobs\ScoreListing;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Services\Enrichment\ListingEnricher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

it('writes the extracted markdown and marks the listing enriched', function () {
    Bus::fake([ScoreListing::class]);

    $listing = Listing::factory()->create([
        'board' => 'larajobs',
        'url' => 'https://apply.workable.com/chirocat/j/ABC',
        'enriched_at' => null,
        'enrichment_source' => null,
        'description' => 'Title: Senior Dev\nCompany: Chirocat',
    ]);

    Http::fake([
        'apply.workable.com/chirocat/j/ABC' => Http::sequence()
            ->push('', 200)
            ->push('<link rel="alternate" type="text/markdown" href="https://apply.workable.com/chirocat/jobs/view/ABC.md"/>', 200),
        'apply.workable.com/chirocat/jobs/view/ABC.md' => Http::response("# Senior Engineer\n\nFull description.", 200),
    ]);

    (new EnrichListing($listing))->handle(app(ListingEnricher::class));

    $listing->refresh();

    expect($listing->description)->toContain('# Senior Engineer')
        ->and($listing->enrichment_source)->toBe('workable_md')
        ->and($listing->enriched_at)->not->toBeNull();
});

it('records source=none and leaves description untouched when extraction fails', function () {
    Bus::fake([ScoreListing::class]);

    $listing = Listing::factory()->create([
        'board' => 'larajobs',
        'url' => 'https://example.com/careers/dev',
        'description' => 'stub metadata',
        'enriched_at' => null,
        'enrichment_source' => null,
    ]);

    Http::fake([
        'example.com/*' => Http::response('', 500),
    ]);

    (new EnrichListing($listing))->handle(app(ListingEnricher::class));

    $listing->refresh();

    expect($listing->description)->toBe('stub metadata')
        ->and($listing->enrichment_source)->toBe('none')
        ->and($listing->enriched_at)->not->toBeNull();
});

it('dispatches ScoreListing for unscored pivots once enrichment finishes', function () {
    Bus::fake([ScoreListing::class]);

    $user = User::factory()->create();
    $target = targetFor($user, ['is_active' => true]);

    $listing = Listing::factory()->create([
        'board' => 'larajobs',
        'url' => 'https://example.com/role',
        'enriched_at' => null,
        'enrichment_source' => null,
    ]);

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
    ]);

    Http::fake([
        'example.com/*' => Http::response('<html><main><h1>Role</h1><p>Description here.</p></main></html>', 200),
    ]);

    (new EnrichListing($listing))->handle(app(ListingEnricher::class));

    Bus::assertDispatched(ScoreListing::class, fn ($job) => $job->listing->id === $listing->id && $job->target->id === $target->id);
});

it('is idempotent — does nothing if the listing is already enriched', function () {
    Bus::fake([ScoreListing::class]);

    $listing = Listing::factory()->create([
        'board' => 'larajobs',
        'description' => 'already good',
        'enriched_at' => now(),
        'enrichment_source' => 'workable_md',
    ]);

    Http::fake([
        '*' => Http::response('should not be called', 500),
    ]);

    (new EnrichListing($listing))->handle(app(ListingEnricher::class));

    Http::assertNothingSent();
    Bus::assertNotDispatched(ScoreListing::class);
});
