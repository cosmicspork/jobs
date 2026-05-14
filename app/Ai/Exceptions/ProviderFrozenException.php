<?php

namespace App\Ai\Exceptions;

use Carbon\CarbonImmutable;
use Laravel\Ai\Exceptions\AiException;
use Throwable;

class ProviderFrozenException extends AiException
{
    public function __construct(
        public readonly string $provider,
        public readonly CarbonImmutable $until,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            "AI provider [{$provider}] is frozen until {$until->toIso8601String()}.",
            previous: $previous,
        );
    }
}
