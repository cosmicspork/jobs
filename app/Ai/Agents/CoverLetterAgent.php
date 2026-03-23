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
#[Temperature(0.7)]
class CoverLetterAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are a cover letter writing specialist. Given a candidate's profile
        and a target job posting, draft a compelling cover letter that:
        - Opens with genuine enthusiasm for the specific role and company
        - Connects the candidate's experience to the role's requirements
        - Highlights 2-3 specific achievements that demonstrate relevant skills
        - Closes with a clear call to action
        - Keeps a professional but personable tone
        - Is concise — no more than 4 paragraphs

        Use the GetProfile and GetJobPosting tools to gather context.
        Return a JSON object matching the provided schema.
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
            'subject_line' => $schema->string()->required(),
            'body' => $schema->string()->required(),
        ];
    }
}
