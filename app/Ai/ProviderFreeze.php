<?php

namespace App\Ai;

use App\Ai\Events\ProviderFrozen;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Exceptions\AiException;

class ProviderFreeze
{
    public static function providerFrozenUntil(string $provider): ?CarbonImmutable
    {
        $iso = Cache::get(self::cacheKey($provider));

        return $iso ? CarbonImmutable::parse($iso) : null;
    }

    public static function freezeProvider(string $provider, CarbonImmutable $until): void
    {
        Cache::put(
            self::cacheKey($provider),
            $until->toIso8601String(),
            $until,
        );
    }

    /**
     * If the exception message indicates the provider has hit a spending
     * or usage limit, freeze the provider in cache, fire ProviderFrozen,
     * and return the regain-access timestamp. Returns null when the
     * message doesn't match (caller should treat as a normal AiException).
     */
    public static function freezeIfUsageLimited(string $provider, AiException $e): ?CarbonImmutable
    {
        $until = self::extractFromMessage($e->getMessage());

        if (! $until) {
            return null;
        }

        self::freezeProvider($provider, $until);

        event(new ProviderFrozen($provider, $until, 'usage_limit'));

        return $until;
    }

    /**
     * Parse a provider exception message for a usage-limit / spending-cap
     * marker and return the regain-access timestamp. Currently matches
     * Anthropic's "regain access on YYYY-MM-DD at HH:MM UTC" wording.
     */
    public static function extractFromMessage(string $message): ?CarbonImmutable
    {
        if (! str_contains($message, 'usage limit')) {
            return null;
        }

        if (preg_match('/regain access on (\d{4}-\d{2}-\d{2})(?: at (\d{2}:\d{2}) UTC)?/i', $message, $m)) {
            $time = $m[2] ?? '00:00';

            return CarbonImmutable::parse("{$m[1]} {$time}", 'UTC');
        }

        return CarbonImmutable::now()->addHours(6);
    }

    private static function cacheKey(string $provider): string
    {
        return "ai_provider_frozen_until:{$provider}";
    }
}
