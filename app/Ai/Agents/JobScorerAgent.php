<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetJobPosting;
use App\Ai\Tools\GetProfile;
use App\Ai\Tools\GetTargetProfile;
use App\Models\TargetProfile;
use App\Models\User;
use App\Relevance;
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

#[Model('anthropic/claude-haiku-4-5')]
#[MaxTokens(2048)]
#[Temperature(0.3)]
class JobScorerAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function instructions(): Stringable|string
    {
        return $this->user->getPrompt('scorer');
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            new GetProfile($this->user),
            new GetTargetProfile($this->target),
            new GetJobPosting,
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
