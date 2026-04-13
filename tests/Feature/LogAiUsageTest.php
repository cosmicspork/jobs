<?php

use App\Ai\Agents\JobScorerAgent;
use App\Listeners\LogAiUsage;
use App\Models\AiUsage;
use App\Models\User;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

function buildEvent(Usage $usage, Meta $meta): AgentPrompted
{
    $response = new AgentResponse('inv-1', '{}', $usage, $meta);

    $user = User::factory()->create();
    $agent = new JobScorerAgent($user);
    $provider = Mockery::mock(TextProvider::class);

    $prompt = new AgentPrompt($agent, 'test', [], $provider, $meta->model ?? 'test');

    return new AgentPrompted('inv-1', $prompt, $response);
}

beforeEach(function () {
    login();
});

it('logs ai usage from an AgentPrompted event', function () {
    $usage = new Usage(
        promptTokens: 1500,
        completionTokens: 500,
        cacheWriteInputTokens: 100,
        cacheReadInputTokens: 50,
        reasoningTokens: 0,
    );

    $meta = new Meta(
        provider: 'anthropic',
        model: 'anthropic/claude-haiku-4-5',
    );

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    expect(AiUsage::count())->toBe(1);

    $record = AiUsage::first();
    expect($record->agent)->toBe('JobScorerAgent')
        ->and($record->provider)->toBe('anthropic')
        ->and($record->model)->toBe('anthropic/claude-haiku-4-5')
        ->and($record->prompt_tokens)->toBe(1500)
        ->and($record->completion_tokens)->toBe(500)
        ->and($record->cache_write_tokens)->toBe(100)
        ->and($record->cache_read_tokens)->toBe(50)
        ->and($record->reasoning_tokens)->toBe(0)
        ->and((float) $record->cost)->toBeGreaterThan(0);
});

it('calculates cost correctly for haiku', function () {
    $usage = new Usage(promptTokens: 1_000_000, completionTokens: 1_000_000);
    $meta = new Meta(provider: 'anthropic', model: 'anthropic/claude-haiku-4-5');

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    // Haiku: $0.80/M input + $4.00/M output = $4.80
    expect(round((float) AiUsage::first()->cost, 2))->toBe(4.80);
});

it('calculates cost correctly for sonnet', function () {
    $usage = new Usage(promptTokens: 1_000_000, completionTokens: 1_000_000);
    $meta = new Meta(provider: 'anthropic', model: 'anthropic/claude-sonnet-4-6');

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    // Sonnet: $3.00/M input + $15.00/M output = $18.00
    expect(round((float) AiUsage::first()->cost, 2))->toBe(18.00);
});

it('includes cache token costs in calculation', function () {
    $usage = new Usage(
        promptTokens: 0,
        completionTokens: 0,
        cacheWriteInputTokens: 1_000_000,
        cacheReadInputTokens: 1_000_000,
    );
    $meta = new Meta(provider: 'anthropic', model: 'anthropic/claude-haiku-4-5');

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    // Haiku cache: $1.00/M write + $0.08/M read = $1.08
    expect(round((float) AiUsage::first()->cost, 2))->toBe(1.08);
});

it('calculates cost correctly for versioned haiku model name', function () {
    $usage = new Usage(promptTokens: 1_000_000, completionTokens: 1_000_000);
    $meta = new Meta(provider: 'anthropic', model: 'anthropic/claude-4.5-haiku-20251001');

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    // Same pricing as haiku alias: $0.80/M input + $4.00/M output = $4.80
    expect(round((float) AiUsage::first()->cost, 2))->toBe(4.80);
});

it('calculates cost correctly for versioned sonnet model name', function () {
    $usage = new Usage(promptTokens: 1_000_000, completionTokens: 1_000_000);
    $meta = new Meta(provider: 'anthropic', model: 'anthropic/claude-4.6-sonnet-20260217');

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    // Same pricing as sonnet alias: $3.00/M input + $15.00/M output = $18.00
    expect(round((float) AiUsage::first()->cost, 2))->toBe(18.00);
});

it('sets cost to zero for unknown models', function () {
    $usage = new Usage(promptTokens: 1000, completionTokens: 500);
    $meta = new Meta(provider: 'openai', model: 'gpt-4o');

    (new LogAiUsage)->handle(buildEvent($usage, $meta));

    expect((float) AiUsage::first()->cost)->toBe(0.0);
});
