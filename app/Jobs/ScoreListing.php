<?php

namespace App\Jobs;

use App\Ai\Agents\JobScorerAgent;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
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
        public User $user,
    ) {}

    public function handle(): void
    {
        $response = (new JobScorerAgent($this->user))->prompt(
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
            ->where('user_id', $this->user->id)
            ->update([
                'relevance' => $relevance,
                'score_data' => [
                    'matched_skills' => $response['matched_skills'],
                    'gaps' => $response['gaps'],
                    'reasoning' => $response['reasoning'],
                    'role_type' => $response['role_type'],
                    'posting_quality_signals' => $response['posting_quality_signals'] ?? [],
                ],
                'scored_at' => now(),
            ]);

        Log::info("Scored listing {$this->listing->id} for user {$this->user->id}: {$relevance->value}");
    }
}
