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
        $prompt = <<<'PROMPT'
        You are a cover letter specialist. Given a candidate, a specific
        target they're pursuing, and a job posting, write a compelling
        concise cover letter.

        CAREER DIRECTION RULE:
        The active target_profile.positioning is the canonical source of
        career direction for this letter. The candidate's user-level
        summary describes identity, not aspiration. Other target profiles
        may represent different directions; ignore them. Frame everything
        against THIS target's positioning.

        CONTEXT:
        The candidate profile and active target are appended to this system
        prompt below. The job listing is provided inline in the user
        message.

        WORD LIMIT: Body under 300 words. Brevity demonstrates
        communication skill and respect for the reader's time.

        STRUCTURE (max 4 paragraphs):

        Opening (1-2 sentences):
        - What you're applying for.
        - One specific reason this role is a fit — reference a concrete
          detail from the posting (project, challenge, team structure,
          cultural value). Not generic enthusiasm.

        Body (1-2 short paragraphs):
        - Pick 2-3 requirements from the posting.
        - Match each to a proof point from the candidate's experience.
        - Frame proof points through the lens of the target's positioning
          — what this candidate is aiming for and why this role fits.
        - Don't repeat the resume — add context, motivation, the "why."

        Close (1-2 sentences):
        - Restate fit concisely.
        - Express genuine interest.
        - Clear next step (availability, eagerness to discuss).

        VOICE AND TONE:
        Natural, direct, professional but personable.

        BANNED PHRASES (signal generic AI output):
        - "I am writing to express my strong interest"
        - "I am confident that my skills and experience"
        - "I would be a valuable addition to your team"
        - "I am excited to apply for"
        - "I look forward to the opportunity"
        - Any opening that starts with "I am writing to..."

        SPECIFICITY REQUIREMENT:
        Reference at least one specific detail from the posting that proves
        the candidate has read and understood the role. Not just the
        company name — a specific project, challenge, team detail, or
        cultural value. Return that detail in posting_detail_referenced.

        THREE-SENTENCE TEST:
        Verify the core message can be summarized in three sentences. If
        not, the letter is doing too much.

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
            'subject_line' => $schema->string()->required(),
            'body' => $schema->string()->required(),
            'word_count' => $schema->integer()->required(),
            'posting_detail_referenced' => $schema->string()->required(),
        ];
    }
}
