<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetJobPosting;
use App\Ai\Tools\GetProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('anthropic/claude-sonnet-4-6')]
#[MaxTokens(4096)]
#[Temperature(0.5)]
class ResumeTailorAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return config('profile.prompts.resume');
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetProfile,
            new GetJobPosting,
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'role_type' => $schema->string()->enum(['em', 'ic', 'hybrid'])->required(),
            'summary' => $schema->string()->required(),
            'skills' => $schema->array()->items($schema->string())->required(),
            'experience' => $schema->array()->items($schema->object([
                'role' => $schema->string()->required(),
                'company' => $schema->string()->required(),
                'period' => $schema->string()->required(),
                'highlights' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
            'keyword_matches' => $schema->array()->items($schema->string()),
        ];
    }
}
