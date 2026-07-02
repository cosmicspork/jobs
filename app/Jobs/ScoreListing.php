<?php

namespace App\Jobs;

use App\Ai\Agents\JobScorerAgent;
use App\Ai\ProviderFreeze;
use App\Jobs\Concerns\FreezesAiProvider;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Relevance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;

class ScoreListing implements ShouldQueue
{
    use FreezesAiProvider, Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        public Listing $listing,
        public TargetProfile $target,
    ) {}

    /**
     * Stepped backoff (seconds) so retries span a wide window rather than
     * hammering a briefly-overloaded provider within ~60s.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function handle(): void
    {
        $user = $this->target->user;

        if ($user->isOverAiCap()) {
            return;
        }

        $provider = config('ai.agents.scorer.provider');

        if (ProviderFreeze::providerFrozenUntil($provider)) {
            return;
        }

        $listingJson = json_encode($this->listing->toAgentPayload(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $agent = new JobScorerAgent($user, $this->target);

        try {
            $response = $agent->prompt(
                "Score this job listing:\n```json\n{$listingJson}\n```",
                provider: $agent->providers() ?: null,
            );
        } catch (AiException $e) {
            if ($this->failIfUsageLimited($provider, $e)) {
                return;
            }

            throw $e;
        }

        if (! isset($response['relevance'])) {
            throw new \RuntimeException(
                "AI response missing required 'relevance' key for listing {$this->listing->id}."
            );
        }

        $relevance = Relevance::from($response['relevance']);

        ListingUser::query()
            ->where('listing_id', $this->listing->id)
            ->where('target_profile_id', $this->target->id)
            ->update([
                'relevance' => $relevance,
                'score_data' => [
                    'fit_score' => $response['fit_score'] ?? null,
                    'matched_skills' => $response['matched_skills'] ?? [],
                    'gaps' => $response['gaps'] ?? [],
                    'reasoning' => $response['reasoning'] ?? null,
                    'posting_quality_signals' => $response['posting_quality_signals'] ?? [],
                ],
                'scored_at' => now(),
            ]);

        Log::info("Scored listing {$this->listing->id} for target {$this->target->id} ({$this->target->name}): {$relevance->value}");
    }
}
