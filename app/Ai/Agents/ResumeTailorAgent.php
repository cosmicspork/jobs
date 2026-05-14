<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetProfile;
use App\Ai\Tools\GetTargetProfile;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(4096)]
#[Temperature(0.5)]
class ResumeTailorAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function provider(): string
    {
        return config('ai.agents.resume_tailor.provider');
    }

    public function model(): string
    {
        return config('ai.agents.resume_tailor.model');
    }

    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        return config('ai.agents.resume_tailor.failover', []);
    }

    public function instructions(): Stringable|string
    {
        return $this->user->getPrompt('resume');
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetProfile($this->user),
            new GetTargetProfile($this->target),
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
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
