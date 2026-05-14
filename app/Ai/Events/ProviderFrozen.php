<?php

namespace App\Ai\Events;

use Carbon\CarbonImmutable;

class ProviderFrozen
{
    public function __construct(
        public readonly string $provider,
        public readonly CarbonImmutable $until,
        public readonly string $reason,
    ) {}
}
