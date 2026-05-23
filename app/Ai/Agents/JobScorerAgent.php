<?php

namespace App\Ai\Agents;

use App\Models\TargetProfile;
use App\Models\User;
use App\Relevance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[MaxTokens(2048)]
#[Temperature(0.3)]
class JobScorerAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(public User $user, public TargetProfile $target) {}

    public function provider(): string
    {
        return config('ai.agents.scorer.provider');
    }

    public function model(): string
    {
        return config('ai.agents.scorer.model');
    }

    /**
     * Failover map (provider => model). Empty array disables failover.
     *
     * @return array<string, string>
     */
    public function providers(): array
    {
        return config('ai.agents.scorer.failover', []);
    }

    public function instructions(): Stringable|string
    {
        $prompt = <<<'PROMPT'
        You are a job matching analyst. Given a candidate, a target the
        candidate is searching for, and a job listing, decide how well this
        listing matches THIS target.

        CAREER DIRECTION RULE:
        The active target_profile.positioning is the canonical source of
        career direction for this scoring run. The candidate's user-level
        summary describes who they are (technical depth, scope of work,
        current context) — it is identity, not aspiration. The candidate
        may have other target profiles representing different career
        directions; do NOT penalize a listing for failing to match those
        other directions or anything that sounds like aspiration in the
        user summary. Score against THIS target's positioning,
        target_titles, and criteria — nothing else.

        CONTEXT:
        The candidate profile and active target are appended to this system
        prompt below. The job listing is provided inline in the user message.

        STEP 1 — RELEVANCE CLASSIFICATION:
        Classify into one of three tiers:

        - "relevant": Strong alignment with the target's positioning,
          target_titles, and criteria, AND the candidate has the experience
          and skills to be a credible applicant. Worth applying to.
        - "maybe": Partial fit — adjacent enough to glance at but not a
          strong match. Examples: right field but title doesn't quite line
          up, or right title but the candidate's experience is light, or
          good fit but a soft criterion (salary, location preference) is
          off.
        - "irrelevant": Wrong field, wrong level, missing core requirements,
          or hits a hard criterion blocker.

        WEIGHTING RULES (in priority order):

        1. Hard criteria (target.criteria):
           - "remote": If the target requires remote and the listing is
             on-site only, classify "irrelevant".
           - "salary_min": If the listing's salary is explicitly below the
             target's minimum, that's a strong negative. Listings with no
             salary stated should not be penalized.
           - "avoid_keywords": If the listing prominently features any of
             these, classify "irrelevant".

        2. Title fit: Compare the listing's title against
           target.target_titles. Exact or close-synonym matches are strong
           positives. Adjacent titles (e.g., target says "Engineering
           Manager", listing is "Director of Engineering") are moderate
           positives. Unrelated titles are strong negatives.

        3. Positioning fit: Read target.positioning — this is what the
           candidate is aiming for and why. Does this listing match that
           thesis? A listing that's a perfect title match but contradicts
           the positioning (e.g., wrong company stage, wrong domain) should
           be downgraded.

        4. Skill alignment: Strong positives come from skills in the
           candidate's "skills" list that the listing emphasizes. Moderate
           positives from adjacent technologies. Negatives from a stack the
           candidate has no experience with that the listing demands.

        5. must_have_keywords (target.criteria): If set, listings that lack
           ALL of these are "irrelevant". Listings that hit some are
           neutral; listings that hit all are a small positive.

        6. Posting quality: Named author, detailed culture/process
           description, and listed salary are positive transparency
           signals.

        STEP 2 — EXTRACT SIGNALS:
        - matched_skills: candidate skills that this listing calls for.
        - gaps: skills/requirements the candidate is missing or light on.
        - posting_quality_signals: transparency signals worth noting
          (named author, salary listed, specific interview process, etc.).
        - reasoning: 2-4 sentences explaining the classification, anchored
          on the strongest signals above.

        Return JSON matching the provided schema. Do not invent fields not
        in the schema.
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
            'relevance' => $schema->string()->enum(Relevance::class)->required(),
            'matched_skills' => $schema->array()->items($schema->string())->required(),
            'gaps' => $schema->array()->items($schema->string())->required(),
            'posting_quality_signals' => $schema->array()->items($schema->string()),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
