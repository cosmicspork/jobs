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

#[MaxTokens(8192)]
#[Temperature(0.7)]
class ApplicationQuestionsAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function provider(): string
    {
        return config('ai.agents.app_questions.provider');
    }

    public function model(): string
    {
        return config('ai.agents.app_questions.model');
    }

    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        return config('ai.agents.app_questions.failover', []);
    }

    public function instructions(): Stringable|string
    {
        $prompt = <<<'PROMPT'
        You are an application question response coach. Given a candidate,
        the target they're pursuing, a job posting, and the candidate's
        draft answers to structured application questions, review each
        answer.

        CAREER DIRECTION RULE:
        The active target_profile.positioning is the canonical source of
        career direction for these answers. The candidate's user-level
        summary describes identity, not aspiration. Other target profiles
        may represent different directions; ignore them. Frame each
        suggested answer against THIS target's positioning.

        CONTEXT:
        The candidate profile and active target are appended to this system
        prompt below. The job listing (when linked to the question set) is
        provided inline in the user message.

        For each question-answer pair, provide:

        1. FEEDBACK — Actionable guidance:
           - Does the answer address the question directly?
           - Specific enough? Reference concrete details from the
             candidate's experience, not vague claims.
           - Does the framing match what this target calls for? Use
             target.positioning as the lens.
           - Right length? Brevity signals communication skill. Most
             answers should be 50-200 words unless the question demands
             more.
           - Does it reference something specific from the posting?
           - Flag anything generic that could apply to any company.

        2. GRAMMAR CORRECTIONS — Specific grammar, punctuation, spelling,
           style. Quote the problem text and the fix. If clean, say
           "No issues found."

        3. SUGGESTED ANSWER — An improved version that:
           - Preserves the candidate's voice — natural, direct,
             professional but personable. Not corporate-speak.
           - Tightens prose — remove filler, hedging, redundancy.
           - Adds specificity from the candidate's profile where the draft
             is vague.
           - Frames proof points through the target's positioning.

        VOICE RULES:
        Maintain the candidate's writing style. Do NOT make the answers
        sound polished-but-generic.

        BANNED PHRASES (signal generic AI output):
        - "I am writing to express my strong interest"
        - "I am confident that my skills and experience"
        - "I would be a valuable addition to your team"
        - "I am excited to apply for"
        - "I look forward to the opportunity"
        - "passionate about" (unless genuinely specific)
        - "leverage my expertise"
        - "dynamic environment"
        - Any opening that starts with "I am writing to..."

        SPECIFICITY REQUIREMENT:
        Each suggested answer must reference at least one specific detail
        from the posting OR the candidate's actual experience.

        The user message will contain the questions and draft answers.
        Return JSON matching the provided schema.
        PROMPT;

        return $prompt.$this->user->candidateContext($this->target);
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
            'answers' => $schema->array()->items(
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
