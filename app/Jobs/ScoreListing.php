<?php

namespace App\Jobs;

use App\Ai\Agents\JobScorerAgent;
use App\Models\Listing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScoreListing implements ShouldQueue
{
    use Queueable;

    public function __construct(public Listing $listing) {}

    public function handle(): void
    {
        $response = (new JobScorerAgent)->prompt(
            "Score this job listing (listing_id: {$this->listing->id})."
        );

        $this->listing->update([
            'score' => $response['score'],
            'score_data' => [
                'matched_skills' => $response['matched_skills'],
                'gaps' => $response['gaps'],
                'reasoning' => $response['reasoning'],
                'salary_match' => $response['salary_match'],
            ],
            'scored_at' => now(),
        ]);

        Log::info("Scored listing {$this->listing->id}: {$response['score']}/100");
    }
}
