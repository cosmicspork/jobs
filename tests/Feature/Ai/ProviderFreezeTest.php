<?php

use App\Ai\Events\ProviderFrozen;
use App\Ai\ProviderFreeze;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Exceptions\AiException;

beforeEach(function () {
    login();
});

it('returns null when no freeze is set', function () {
    expect(ProviderFreeze::providerFrozenUntil('anthropic'))->toBeNull();
});

it('round-trips a freeze through the cache', function () {
    $until = CarbonImmutable::now()->addHours(3);

    ProviderFreeze::freezeProvider('anthropic', $until);

    $stored = ProviderFreeze::providerFrozenUntil('anthropic');

    expect($stored)->not->toBeNull()
        ->and($stored->toIso8601String())->toBe($until->toIso8601String());
});

it('keys freezes by provider so freezing one does not freeze another', function () {
    ProviderFreeze::freezeProvider('anthropic', CarbonImmutable::now()->addHour());

    expect(ProviderFreeze::providerFrozenUntil('anthropic'))->not->toBeNull()
        ->and(ProviderFreeze::providerFrozenUntil('openrouter'))->toBeNull();
});

it('parses Anthropic usage-limit messages with a regain-access date', function (string $message, string $expectedDate, int $expectedHour) {
    $parsed = ProviderFreeze::extractFromMessage($message);

    expect($parsed)->not->toBeNull()
        ->and($parsed->toDateString())->toBe($expectedDate)
        ->and($parsed->hour)->toBe($expectedHour);
})->with([
    'spending cap with explicit UTC time' => [
        'Anthropic Error [400]: invalid_request_error - You have reached your specified API usage limits. You will regain access on 2026-06-01 at 00:00 UTC.',
        '2026-06-01',
        0,
    ],
    'spending cap with mid-day reset time' => [
        'You have reached your specified API usage limits. You will regain access on 2026-07-01 at 15:30 UTC.',
        '2026-07-01',
        15,
    ],
    'spending cap with date only (no time component)' => [
        'You have reached your usage limit. You will regain access on 2026-06-01.',
        '2026-06-01',
        0,
    ],
]);

it('returns null for unrelated AiException messages', function (string $message) {
    expect(ProviderFreeze::extractFromMessage($message))->toBeNull();
})->with([
    'overloaded' => 'Anthropic Error [529]: overloaded_error - service is currently overloaded',
    'rate limited' => 'Anthropic Error [429]: rate_limit_error',
    'auth error' => 'Anthropic Error [401]: authentication_error - invalid api key',
    'internal server error' => 'Anthropic Error [500]: internal server error',
]);

it('falls back to a 6-hour freeze when the message mentions a usage limit but no date can be parsed', function () {
    $before = CarbonImmutable::now();

    $parsed = ProviderFreeze::extractFromMessage('You have reached your usage limit for the day.');

    expect($parsed)->not->toBeNull()
        ->and($parsed->greaterThanOrEqualTo($before->addHours(6)))->toBeTrue()
        ->and($parsed->lessThan($before->addHours(6)->addMinute()))->toBeTrue();
});

it('freezeIfUsageLimited writes cache, fires event, and returns the timestamp', function () {
    Event::fake([ProviderFrozen::class]);

    $e = new AiException(
        'Anthropic Error [400]: invalid_request_error - You have reached your specified API usage limits. You will regain access on 2026-06-01 at 00:00 UTC.',
        400,
    );

    $until = ProviderFreeze::freezeIfUsageLimited('anthropic', $e);

    expect($until)->not->toBeNull()
        ->and($until->toDateString())->toBe('2026-06-01')
        ->and(ProviderFreeze::providerFrozenUntil('anthropic'))->not->toBeNull();

    Event::assertDispatched(
        ProviderFrozen::class,
        fn (ProviderFrozen $event) => $event->provider === 'anthropic'
            && $event->reason === 'usage_limit'
    );
});

it('freezeIfUsageLimited returns null and does nothing on unrelated AiException', function () {
    Event::fake([ProviderFrozen::class]);

    $e = new AiException('Anthropic Error [500]: internal_server_error', 500);

    $until = ProviderFreeze::freezeIfUsageLimited('anthropic', $e);

    expect($until)->toBeNull()
        ->and(ProviderFreeze::providerFrozenUntil('anthropic'))->toBeNull();

    Event::assertNotDispatched(ProviderFrozen::class);
});
