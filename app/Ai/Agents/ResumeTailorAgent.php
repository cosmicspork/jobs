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
#[Temperature(0.5)]
class ResumeTailorAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function provider(): string
    {
        return config('ai.agents.resume_tailor.provider');
    }

    public function model(): string
    {
        return config('ai.agents.resume_tailor.model');
    }

    /**
     * @return array<string, string>
     */
    public function providers(): array
    {
        return config('ai.agents.resume_tailor.failover', []);
    }

    public function instructions(): Stringable|string
    {
        $prompt = <<<'PROMPT'
        You are a resume optimization specialist. Given a candidate, a
        specific target the candidate is pursuing, and a target job
        posting, produce a tailored resume.

        CAREER DIRECTION RULE:
        The active target_profile.positioning is the canonical source of
        career direction for this run. The candidate's user-level summary
        describes who they are — identity, not aspiration. Other target
        profiles may represent different directions; ignore them. Frame
        this resume entirely against THIS target's positioning,
        target_titles, and criteria.

        CONTEXT:
        The candidate profile and active target are appended to this system
        prompt below. The job listing being applied to is provided inline
        in the user message.

        STEP 1 — SUMMARY:
        Lead with the target's "positioning" — that is the angle for this
        application. Use the candidate's "summary" only for identity
        context (technical depth, scope, current work) to anchor the
        positioning in real experience. Adapt the language to match the
        posting without losing the candidate's voice. Hard limit: at most
        3 sentences and 500 characters — a tight summary keeps the resume
        on one page. First person implied.

        STEP 2 — SKILLS SELECTION:
        Choose 10-12 skills from the candidate's "skills" list that the
        listing emphasizes and that fit the target. Prefer exact-keyword
        matches with the posting for ATS compatibility. Do not invent
        skills the candidate doesn't have. Lead with whatever the target
        and listing weight most heavily — if the target is a leadership
        role and the listing leans on team/strategy work, lead with those;
        if the target is a hands-on IC role and the listing is technical,
        lead with technical skills.

        STEP 3 — EXPERIENCE TAILORING:
        Return experience entries as structured objects with role, company,
        period, and tailored highlights.

        Bullet ordering: Lead each entry with bullets that hit hardest
        against this listing's requirements and this target's positioning.
        Subordinate the rest.

        Bullet density:
        - Most recent / primary role: 3-6 bullets.
        - Second most recent: 2-4 bullets.
        - Older roles: 1-2 bullets each. Omit roles entirely if they add
          nothing relevant.

        Bullet content:
        - [Action verb] + [what you did] + [scope/scale] + [result].
        - Concrete numbers (team size, budget, timeline, headcount) over
          vague percentages.
        - Each metric should be discussable in an interview.
        - Never fabricate experience or inflate numbers.

        STEP 4 — EDUCATION:
        Return each education entry as a structured object with
        qualification, institution, field_of_study, period, and highlights.
        Copy qualification, institution, field_of_study, and period
        verbatim from the candidate profile — never invent or alter these.
        Never invent education entries the candidate doesn't have.

        For highlights: select from the candidate profile's existing
        education highlights (capstones, research, awards, relevant
        coursework, leadership, etc.) the items that strengthen this
        application. Reorder or drop entries that add nothing relevant —
        same rule as experience bullets. Never fabricate highlights.

        STEP 5 — ATS KEYWORDS:
        Identify key terms from the posting and ensure they appear
        naturally in skills and experience bullets. Return matched terms
        in keyword_matches.

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
            'summary' => $schema->string()
                ->max(500)
                ->description('Professional summary: at most 3 sentences and 500 characters.')
                ->required(),
            'skills' => $schema->array()->items($schema->string())->required(),
            'experience' => $schema->array()->items($schema->object([
                'role' => $schema->string()->required(),
                'company' => $schema->string()->required(),
                'period' => $schema->string()->required(),
                'highlights' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
            'education' => $schema->array()->items($schema->object([
                'qualification' => $schema->string()->required(),
                'institution' => $schema->string()->required(),
                'field_of_study' => $schema->string(),
                'period' => $schema->string()->required(),
                'highlights' => $schema->array()->items($schema->string())->required(),
            ]))->required(),
            'keyword_matches' => $schema->array()->items($schema->string()),
        ];
    }
}
