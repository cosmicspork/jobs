<?php

use App\Ai\Agents\JobScorerAgent;
use App\Jobs\ScoreListing;
use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\AiException;

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

it('inlines the listing payload into the user prompt', function () {
    $listing = Listing::factory()->create([
        'title' => 'Senior Laravel Engineer',
        'company' => 'TestCo',
    ]);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $this->target->id,
    ]);

    $captured = null;
    JobScorerAgent::fake(function ($prompt) use (&$captured) {
        $captured = (string) $prompt;

        return [
            'relevance' => 'relevant',
            'matched_skills' => [],
            'gaps' => [],
            'reasoning' => 'ok',
        ];
    });

    (new ScoreListing($listing, $this->target))->handle();

    expect($captured)
        ->toContain('Score this job listing')
        ->toContain('Senior Laravel Engineer')
        ->toContain('TestCo')
        ->toContain($listing->id)
        ->not->toContain('listing_id: '.$listing->id);
});

it('exposes Anthropic prompt-cache control via providerOptions', function () {
    $agent = new JobScorerAgent($this->user, $this->target);

    expect($agent->providerOptions(Lab::Anthropic))
        ->toBe(['cache_control' => ['type' => 'ephemeral']])
        ->and($agent->providerOptions('anthropic'))
        ->toBe(['cache_control' => ['type' => 'ephemeral']])
        ->and($agent->providerOptions(Lab::OpenRouter))
        ->toBe([]);
});

it('short-circuits scoring while the provider is frozen', function () {
    JobScorerAgent::fake()->preventStrayPrompts();

    ScoreListing::freezeProvider(
        config('ai.agents.scorer.provider'),
        CarbonImmutable::now()->addDay(),
    );

    (new ScoreListing($this->listing, $this->target))->handle();

    expect($this->pivot->fresh()->scored_at)->toBeNull();
    JobScorerAgent::assertNeverPrompted();
});

it('freezes the provider when a usage-limit AiException is thrown', function () {
    JobScorerAgent::fake(function () {
        throw new AiException(
            'Anthropic Error [400]: invalid_request_error - You have reached your specified API usage limits. You will regain access on 2026-06-01 at 00:00 UTC.',
            400,
        );
    });

    // $this->fail() is a noop without a queue job, so handle() returns cleanly
    // and the side-effects (cache freeze + unscored pivot) are what the test verifies.
    (new ScoreListing($this->listing, $this->target))->handle();

    $frozenUntil = ScoreListing::providerFrozenUntil(config('ai.agents.scorer.provider'));

    expect($frozenUntil)->not->toBeNull()
        ->and($frozenUntil->toDateString())->toBe('2026-06-01')
        ->and($frozenUntil->hour)->toBe(0)
        ->and($this->pivot->fresh()->scored_at)->toBeNull();
});

it('rethrows non-usage-limit AiExceptions so existing retry handling fires', function () {
    JobScorerAgent::fake(function () {
        throw new AiException('Anthropic Error [500]: internal_server_error - transient failure', 500);
    });

    $job = new ScoreListing($this->listing, $this->target);

    expect(fn () => $job->handle())->toThrow(AiException::class);
    expect(ScoreListing::providerFrozenUntil(config('ai.agents.scorer.provider')))->toBeNull();
});
