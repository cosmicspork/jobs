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

        STEP 1 — RELEVANCE CLASSIFICATION (STRICT):
        This search is deliberately high-precision. Most listings are NOT
        relevant. Default to "irrelevant" unless the listing clears a high bar.
        Classify into one of three tiers:

        - "relevant" (fit_score >= 75): The listing aligns on ALL THREE of:
            (a) the target's required stack / seniority (see rule 4),
            (b) target_titles — an exact or close-synonym title match,
            (c) the positioning thesis in target.positioning.
          A partial or adjacent match on only one or two of (a)/(b)/(c) does
          NOT qualify. The candidate must also be a credible applicant. This is
          a role worth applying to today.
        - "maybe" (fit_score 55-74): RARE. Reserve for a genuine near-miss a
          serious candidate would still glance at — e.g. all three axes align
          but a single soft criterion is off (stated salary slightly low, a
          secondary location), or the title is one notch off an otherwise
          perfect match. A generalist role that merely uses some of the
          candidate's languages (TypeScript, React, Node, Python, Go) but is
          NOT what the target is aiming for is "irrelevant", NOT "maybe".
        - "irrelevant" (fit_score < 55): Fails any of (a)/(b)/(c), wrong
          field/level, missing the target's required stack, a generalist role
          overlapping only on peripheral skills, or any hard-criterion blocker.

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

        4. Skill alignment — SCOPED TO THE TARGET, NOT THE CANDIDATE'S WHOLE
           CATALOG: The relevant skill set for this run is the target's
           REQUIRED stack, derived from target.positioning,
           target.criteria.must_have_keywords, and target.target_titles. ONLY
           skills in that target-required set count as positive signal. A match
           on a peripheral or generalist skill the candidate happens to list
           (e.g. TypeScript, React, Node) that the TARGET does not call for is
           NEUTRAL and must NOT upgrade relevance. Negatives still come from a
           target-required stack the candidate lacks. Populate matched_skills
           ONLY with target-required skills the listing emphasizes.

        5. must_have_keywords (target.criteria): If set, listings that lack
           ALL of these are "irrelevant". Listings that hit some are
           neutral; listings that hit all are a small positive.

        6. Posting quality: Named author, detailed culture/process
           description, and listed salary are positive transparency
           signals.

        STEP 2 — FIT SCORE:
        Emit fit_score (an integer 0-100) consistent with the tier: >= 75 for
        "relevant", 55-74 for "maybe", < 55 for "irrelevant". The integer and
        the relevance enum MUST agree.

        STEP 3 — EXTRACT SIGNALS:
        - matched_skills: target-required skills that this listing calls for
          (per rule 4 — not generic overlap).
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
            'fit_score' => $schema->integer()->min(0)->max(100)
                ->description('Overall fit 0-100. >=75 relevant, 55-74 maybe, else irrelevant. Must agree with relevance.')
                ->required(),
            'matched_skills' => $schema->array()->items($schema->string())->required(),
            'gaps' => $schema->array()->items($schema->string())->required(),
            'posting_quality_signals' => $schema->array()->items($schema->string()),
            'reasoning' => $schema->string()->required(),
        ];
    }
}
