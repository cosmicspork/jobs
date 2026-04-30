<?php

namespace App\Ai\Tools;

use App\Models\TargetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetTargetProfile implements Tool
{
    public function __construct(private TargetProfile $target) {}

    public function description(): Stringable|string
    {
        return 'Returns the target the candidate is matching against: target name, positioning statement, target job titles, and matching criteria (remote, salary floor, locations, must-have/avoid keywords).';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode([
            'name' => $this->target->name,
            'positioning' => $this->target->positioning,
            'target_titles' => $this->target->target_titles ?? [],
            'criteria' => $this->target->criteria ?? [],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
