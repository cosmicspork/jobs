<?php

namespace App\Ai\Agents;

use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(4096)]
#[Temperature(0.5)]
class ResumeTailorAgent implements Agent, HasProviderOptions, HasStructuredOutput
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
        return $this->user->getAgentInstructions('resume', $this->target);
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
            'education' => $schema->array()->items($schema->string())->required(),
            'keyword_matches' => $schema->array()->items($schema->string()),
        ];
    }
}
