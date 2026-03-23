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

#[Model('anthropic/claude-haiku-4-5-20251001')]
#[MaxTokens(2048)]
#[Temperature(0.3)]
class JobScorerAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a job matching analyst. Given a candidate profile and a job
        listing, score the listing 0-100 for relevance and provide:
        - score: integer 0-100
        - matched_skills: array of skills that align
        - gaps: array of required skills the candidate lacks
        - reasoning: 2-3 sentence explanation
        - salary_match: boolean (true if salary overlaps with candidate preferences, or if no salary info available)

        Use the GetProfile tool to retrieve the candidate's profile and the
        GetJobPosting tool to retrieve the job listing details.
        Respond as JSON matching the provided schema.
        PROMPT;
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
            'score' => $schema->integer()->required(),
            'matched_skills' => $schema->array($schema->string())->required(),
            'gaps' => $schema->array($schema->string())->required(),
            'reasoning' => $schema->string()->required(),
            'salary_match' => $schema->boolean()->required(),
        ];
    }
}
