<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetProfile implements Tool
{
    public function description(): Stringable|string
    {
        return 'Returns the candidate profile including skills, experience, and job preferences.';
    }

    public function handle(Request $request): Stringable|string
    {
        /** @var array<string, mixed> $profile */
        $profile = Arr::except(config('profile'), 'prompts');

        return json_encode($profile, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
