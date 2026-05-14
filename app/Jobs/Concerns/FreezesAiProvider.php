<?php

namespace App\Jobs\Concerns;

use App\Ai\Exceptions\ProviderFrozenException;
use App\Ai\ProviderFreeze;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;

/**
 * Job-side wrapper around ProviderFreeze. Use on queued jobs that prompt
 * AI agents: call failIfUsageLimited() inside an AiException catch block
 * to freeze the provider (when applicable) and route the failed job
 * through $this->fail() so it does not retry.
 */
trait FreezesAiProvider
{
    /**
     * Returns true if the provider was just frozen and $this->fail() has
     * been called. The caller should return immediately. Returns false
     * when the exception does not match a usage-limit pattern; the caller
     * should re-throw to let normal retry/failure logic apply.
     */
    protected function failIfUsageLimited(string $provider, AiException $e): bool
    {
        $until = ProviderFreeze::freezeIfUsageLimited($provider, $e);

        if (! $until) {
            return false;
        }

        Log::warning("AI provider [{$provider}] hit usage limit; freezing until {$until->toIso8601String()}.");

        $this->fail(new ProviderFrozenException($provider, $until, $e));

        return true;
    }
}
