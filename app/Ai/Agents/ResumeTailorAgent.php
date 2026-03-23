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
        return <<<'PROMPT'
        You are a resume optimization specialist. Given a candidate's full
        experience and a target job posting, produce a tailored resume by:
        - Rewriting the professional summary to align with the role
        - Reordering and emphasizing relevant skills
        - Adjusting bullet point emphasis on experience entries
        - Keeping all facts truthful — never fabricate experience

        Use the GetProfile and GetJobPosting tools to gather context.
        Return a JSON object matching the provided schema with the rewritten sections.
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
            'summary' => $schema->string()->required(),
            'skills' => $schema->array($schema->string())->required(),
            'experience_highlights' => $schema->array($schema->string())->required(),
        ];
    }
}
