<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetProfile;
use App\Ai\Tools\GetTargetProfile;
use App\Models\TargetProfile;
use App\Models\User;
use App\Relevance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2048)]
#[Temperature(0.3)]
class JobScorerAgent implements Agent, HasProviderOptions, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function provider(): string
    {
        return config('ai.agents.scorer.provider');
    }

    public function model(): string
    {
        return config('ai.agents.scorer.model');
    }

    public function instructions(): Stringable|string
    {
        return $this->user->getPrompt('scorer');
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::Anthropic, 'anthropic' => ['cache_control' => ['type' => 'ephemeral']],
            default => [],
        };
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
            'relevance' => $schema->string()->enum(Relevance::class)->required(),
            'matched_skills' => $schema->array()->items($schema->string())->required(),
            'gaps' => $schema->array()->items($schema->string())->required(),
            'posting_quality_signals' => $schema->array()->items($schema->string()),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
