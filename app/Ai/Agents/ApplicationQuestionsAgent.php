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
#[MaxTokens(8192)]
#[Temperature(0.7)]
class ApplicationQuestionsAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return config('profile.prompts.application_questions');
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
            'answers' => $schema->array(
                $schema->object([
                    'question_index' => $schema->integer()->required(),
                    'feedback' => $schema->string()->required(),
                    'grammar_corrections' => $schema->string()->required(),
                    'suggested_answer' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
