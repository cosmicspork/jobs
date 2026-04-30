<?php

namespace App\Ai\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetProfile implements Tool
{
    public function __construct(private User $user) {}

    public function description(): Stringable|string
    {
        return 'Returns the candidate identity: name, title, summary, skills, experience, and education. Pair this with GetTargetProfile for the role-specific positioning and criteria.';
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode($this->user->getProfileData(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
