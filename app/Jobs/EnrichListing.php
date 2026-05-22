<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Models\ListingUser;
use App\Services\Enrichment\ListingEnricher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Resolves a listing's URL through any redirects, extracts the real job
 * description from the destination (Workable .md endpoint, generic HTML),
 * writes it back to listings.description, and dispatches ScoreListing for
 * each unscored pivot. This is the gate that lets ScoreListings::handle()
 * delay scoring of LaraJobs rows until a usable description exists.
 */
class EnrichListing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public Listing $listing) {}

    public function handle(ListingEnricher $enricher): void
    {
        if ($this->listing->enriched_at !== null) {
            return;
        }

        $result = $enricher->enrich($this->listing);

        $updates = [
            'enriched_at' => now(),
            'enrichment_source' => $result['source'],
        ];

        if ($result['markdown'] !== null) {
            $updates['description'] = $result['markdown'];
        }

        $this->listing->update($updates);

        Log::info("Enriched listing {$this->listing->id} via {$result['source']} (".strlen((string) $result['markdown']).' chars)');

        // Dispatch scoring for any pivots that were created before enrichment
        // completed. ScoreListings::processPivot will pick up the rest via
        // its scored_at IS NULL chunk loop.
        ListingUser::query()
            ->where('listing_id', $this->listing->id)
            ->whereNull('scored_at')
            ->with('targetProfile')
            ->get()
            ->each(function (ListingUser $pivot): void {
                if ($pivot->targetProfile?->is_active) {
                    ScoreListing::dispatch($this->listing, $pivot->targetProfile);
                }
            });
    }
}
