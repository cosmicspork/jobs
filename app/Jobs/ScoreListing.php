<?php

namespace App\Jobs;

use App\Ai\Agents\JobScorerAgent;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Relevance;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScoreListing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Listing $listing,
        public TargetProfile $target,
    ) {}

    public function handle(): void
    {
        $user = $this->target->user;

        if ($user->isOverAiCap()) {
            return;
        }

        $response = (new JobScorerAgent($user, $this->target))->prompt(
            "Score this job listing (listing_id: {$this->listing->id})."
        );

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
                    'matched_skills' => $response['matched_skills'],
                    'gaps' => $response['gaps'],
                    'reasoning' => $response['reasoning'],
                    'posting_quality_signals' => $response['posting_quality_signals'] ?? [],
                ],
                'scored_at' => now(),
            ]);

        Log::info("Scored listing {$this->listing->id} for target {$this->target->id} ({$this->target->name}): {$relevance->value}");
    }
}
