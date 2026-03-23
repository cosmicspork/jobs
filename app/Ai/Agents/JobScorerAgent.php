<?php

namespace App\Ai\Agents;

use App\Ai\Tools\GetJobPosting;
use App\Ai\Tools\GetProfile;
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

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a job matching analyst. Given a candidate profile and a job
        listing, classify the listing into one of three relevance tiers:

        - "relevant": The candidate should review this listing. Strong alignment
          with their career goals, skills, and preferences. This is the tier for
          listings worth applying to.
        - "maybe": Partial fit — worth a glance but not a strong match. Good tech
          overlap but wrong role type, or right role but significant skill gaps.
        - "irrelevant": Not a match. Wrong field, wrong level, missing most
          required skills, or hard blockers like on-site only when candidate
          requires remote.

        CRITICAL WEIGHTING RULES:
        - The candidate's stated career direction and role-type preference is the
          MOST important factor. If they want management roles, an IC role with
          perfect tech match is "maybe" at best — never "relevant".
        - Remote preference is a hard constraint. On-site-only roles are "irrelevant"
          unless the listing explicitly offers remote.
        - Tech stack overlap alone is not enough for "relevant". The role must also
          align with the candidate's career goals.
        - An engineering management role with moderate tech overlap is more relevant
          than an IC role with perfect tech overlap.

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
            'relevance' => $schema->string()->enum(Relevance::class)->required(),
            'matched_skills' => $schema->array()->items($schema->string())->required(),
            'gaps' => $schema->array()->items($schema->string())->required(),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
