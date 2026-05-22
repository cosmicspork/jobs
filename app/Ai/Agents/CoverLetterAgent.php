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
#[Temperature(0.7)]
class CoverLetterAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function provider(): string
    {
        return config('ai.agents.cover_letter.provider');
    }

    public function model(): string
    {
        return config('ai.agents.cover_letter.model');
    }

    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        return config('ai.agents.cover_letter.failover', []);
    }

    public function instructions(): Stringable|string
    {
        return $this->user->getAgentInstructions('cover_letter', $this->target);
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
            'subject_line' => $schema->string()->required(),
            'body' => $schema->string()->required(),
            'word_count' => $schema->integer()->required(),
            'posting_detail_referenced' => $schema->string()->required(),
        ];
    }
}
