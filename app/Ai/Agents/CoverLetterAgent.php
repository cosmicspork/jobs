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
        return config('profile.prompts.cover_letter');
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
            'word_count' => $schema->integer()->required(),
            'posting_detail_referenced' => $schema->string()->required(),
        ];
    }
}
