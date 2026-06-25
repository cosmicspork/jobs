<?php

use App\Jobs\ScoreListing;
use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Models\User;
use App\Relevance;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->user = login(User::factory()->ic()->create());
    $this->target = $this->user->targetProfiles()->first();
});

/**
 * Create a scored pivot for the given user + target.
 */
function scoredPivot(
    User $user,
    string $targetId,
    Relevance $relevance,
    array $listingAttrs = [],
    array $scoreData = ['matched_skills' => ['Laravel', 'PHP'], 'reasoning' => 'ok'],
): ListingUser {
    $listing = Listing::factory()->create(array_merge([
        'remote' => true,
        'salary_max' => null,
        'description' => 'Senior Laravel engineer building a SaaS in PHP.',
    ], $listingAttrs));

    return ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $targetId,
        'relevance' => $relevance,
        'score_data' => $scoreData,
        'scored_at' => now()->subHour(),
    ]);
}

/**
 * Set must_have_keywords on a target without disturbing its other criteria.
 */
function requireKeywords(TargetProfile $target, array $keywords): void
{
    $target->update(['criteria' => array_merge((array) $target->criteria, [
        'must_have_keywords' => $keywords,
    ])]);
}

it('Pass A demotes a now-blocked relevant pivot to irrelevant+filtered and is idempotent', function () {
    $pivot = scoredPivot($this->user, $this->target->id, Relevance::Relevant, ['description' => 'A great Node.js role, no PHP here.']);

    // Tighten criteria so the listing no longer satisfies must-have.
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify')
        ->expectsOutputToContain('demoted (filter) 1')
        ->assertSuccessful();

    $pivot->refresh();
    expect($pivot->relevance)->toBe(Relevance::Irrelevant)
        ->and($pivot->score_data['filter_reason'])->toBe('missing_must_have')
        ->and($pivot->scored_at)->not->toBeNull(); // re-gate, not re-score

    // Second run: the now-irrelevant pivot falls out of the scan set.
    $this->artisan('jobs:reclassify')
        ->expectsOutputToContain('demoted (filter) 0')
        ->assertSuccessful();
});

it('--dry-run reports demotions without changing anything', function () {
    $pivot = scoredPivot($this->user, $this->target->id, Relevance::Relevant, ['description' => 'A great Node.js role, no PHP here.']);
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify --dry-run')
        ->expectsOutputToContain('[DRY-RUN] | scanned 1 | demoted (filter) 1')
        ->assertSuccessful();

    expect($pivot->refresh()->relevance)->toBe(Relevance::Relevant);
});

it('Pass B demotes a relevant pivot whose matched_skills lack any core keyword', function () {
    // Listing text contains "laravel" so Pass A must-have passes; but the prior
    // matched_skills don't, so Pass B fires.
    $pivot = scoredPivot(
        $this->user,
        $this->target->id,
        Relevance::Relevant,
        ['description' => 'Senior Laravel engineer.'],
        ['matched_skills' => ['React', 'Node.js'], 'reasoning' => 'generalist overlap'],
    );
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify')
        ->expectsOutputToContain('demoted (heuristic) 1')
        ->assertSuccessful();

    $pivot->refresh();
    expect($pivot->relevance)->toBe(Relevance::Maybe)
        ->and($pivot->score_data['reclassified'])->toBe('no_core_skill');
});

it('Pass B demotes a maybe pivot with no core skill all the way to irrelevant', function () {
    $pivot = scoredPivot(
        $this->user,
        $this->target->id,
        Relevance::Maybe,
        ['description' => 'Senior Laravel engineer.'],
        ['matched_skills' => ['React'], 'reasoning' => 'overlap'],
    );
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify')->assertSuccessful();

    expect($pivot->refresh()->relevance)->toBe(Relevance::Irrelevant);
});

it('Pass B is graceful when score_data has no matched_skills key', function () {
    $pivot = scoredPivot(
        $this->user,
        $this->target->id,
        Relevance::Relevant,
        ['description' => 'Senior Laravel engineer.'],
        ['reasoning' => 'no skills recorded'],
    );
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify')->assertSuccessful();

    expect($pivot->refresh()->relevance)->toBe(Relevance::Maybe);
});

it('--rescore nulls scored_at on survivors and re-queues them through jobs:score', function () {
    Queue::fake();

    $pivot = scoredPivot(
        $this->user,
        $this->target->id,
        Relevance::Relevant,
        ['description' => 'Senior Laravel engineer building in PHP.'],
        ['matched_skills' => ['Laravel', 'PHP'], 'reasoning' => 'strong fit'],
    );
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify --rescore --limit=5')
        ->expectsOutputToContain('survivors 1')
        ->assertSuccessful();

    expect($pivot->refresh()->scored_at)->toBeNull();
    Queue::assertPushed(ScoreListing::class, 1);
});

it('--rescore respects the monthly AI cap', function () {
    Queue::fake();
    Mail::fake();
    config(['scoring.monthly_cap_usd' => 1.0]);

    AiUsage::factory()->create(['user_id' => $this->user->id, 'cost' => 5.0]);

    scoredPivot(
        $this->user,
        $this->target->id,
        Relevance::Relevant,
        ['description' => 'Senior Laravel engineer building in PHP.'],
        ['matched_skills' => ['Laravel'], 'reasoning' => 'fit'],
    );
    requireKeywords($this->target, ['laravel']);

    $this->artisan('jobs:reclassify --rescore --limit=5')->assertSuccessful();

    Queue::assertNotPushed(ScoreListing::class);
});

it('--user scoping leaves other users untouched', function () {
    $pivot = scoredPivot($this->user, $this->target->id, Relevance::Relevant, ['description' => 'Node.js only role.']);
    requireKeywords($this->target, ['laravel']);

    $otherId = $this->user->id + 999;

    $this->artisan("jobs:reclassify --user={$otherId}")
        ->expectsOutputToContain('demoted (filter) 0')
        ->assertSuccessful();

    expect($pivot->refresh()->relevance)->toBe(Relevance::Relevant);
});

it('never touches pivots whose target is inactive', function () {
    $inactive = targetFor($this->user, ['is_active' => false, 'criteria' => [
        'remote' => true, 'salary_min' => null, 'locations' => ['Remote'],
        'must_have_keywords' => ['laravel'], 'avoid_keywords' => [],
    ]]);

    $listing = Listing::factory()->create(['remote' => true, 'salary_max' => null, 'description' => 'Node.js only.']);
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $inactive->id,
        'relevance' => Relevance::Relevant,
        'score_data' => ['matched_skills' => ['React']],
        'scored_at' => now()->subHour(),
    ]);

    $this->artisan('jobs:reclassify')
        ->expectsOutputToContain('scanned 0')
        ->assertSuccessful();

    expect($pivot->refresh()->relevance)->toBe(Relevance::Relevant);
});
