<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Scrapers\ScraperInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScrapeBoard implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(
        public string $boardKey,
        public string $scraperClass,
    ) {}

    public function handle(): void
    {
        /** @var ScraperInterface $scraper */
        $scraper = app($this->scraperClass);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = iterator_to_array($scraper->scrape(), preserve_keys: false);
        $total = count($rows);

        if ($total === 0) {
            Log::info("Scraped {$this->boardKey}: 0 listings found.");

            return;
        }

        $now = now();
        $existingListings = Listing::query()
            ->whereIn('source_url', array_column($rows, 'source_url'))
            ->get(['id', 'source_url', 'manually_edited_at'])
            ->keyBy('source_url');

        /** @var array<int, array<string, mixed>> $fullPayload */
        $fullPayload = [];
        /** @var array<int, array<string, mixed>> $metadataPayload */
        $metadataPayload = [];
        /** @var array<int, string> $newListingIds */
        $newListingIds = [];

        foreach ($rows as $data) {
            $id = (string) Str::ulid();
            $existing = $existingListings->get($data['source_url']);

            if ($existing === null) {
                $newListingIds[] = $id;
            }

            $row = [
                'id' => $id,
                'title' => $data['title'],
                'company' => $data['company'],
                'url' => $data['url'],
                'source_url' => $data['source_url'],
                'description' => $data['description'],
                'salary_min' => $data['salary_min'],
                'salary_max' => $data['salary_max'],
                'remote' => (int) (bool) $data['remote'],
                'board' => $this->boardKey,
                'raw_data' => json_encode($data['raw_data']),
                'scraped_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($existing?->manually_edited_at !== null) {
                $metadataPayload[] = $row;
            } else {
                $fullPayload[] = $row;
            }
        }

        $requiresEnrichment = (bool) config("boards.{$this->boardKey}.requires_enrichment", false);

        // Boards whose scraper already delivers a real description are
        // marked enriched at insertion time so the scoring gate lets them
        // through immediately. Enrichment-required boards stay null until
        // EnrichListing rewrites the description.
        if (! $requiresEnrichment) {
            foreach ($fullPayload as &$row) {
                $row['enriched_at'] = $now;
                $row['enrichment_source'] = 'inline';
            }
            unset($row);
        }

        // Columns always refreshed by the scraper, even for user-edited rows.
        $metadataColumns = ['board', 'raw_data', 'scraped_at', 'updated_at'];

        // Full column set applied to new listings and those the user has not
        // manually edited. User-edited rows only receive $metadataColumns so
        // their corrections survive re-scrapes.
        $columnsToUpdate = ['title', 'company', 'url', 'salary_min', 'salary_max',
            'remote', 'board', 'raw_data', 'scraped_at', 'updated_at'];

        // For boards that publish stub descriptions and rely on
        // EnrichListing to write the real one, don't overwrite an
        // enriched description on re-scrape — we'd lose the good copy
        // until the next enrichment cycle.
        if (! $requiresEnrichment) {
            $columnsToUpdate[] = 'description';
            $columnsToUpdate[] = 'enriched_at';
            $columnsToUpdate[] = 'enrichment_source';
        }

        if ($fullPayload !== []) {
            Listing::query()->upsert($fullPayload, ['source_url'], $columnsToUpdate);
        }

        if ($metadataPayload !== []) {
            Listing::query()->upsert($metadataPayload, ['source_url'], $metadataColumns);
        }

        if ($requiresEnrichment) {
            // Newly-created rows from this board need their description
            // resolved from the linked ATS/career page before scoring.
            foreach ($newListingIds as $listingId) {
                /** @var Listing|null $listing */
                $listing = Listing::find($listingId);

                if ($listing !== null) {
                    EnrichListing::dispatch($listing);
                }
            }
        }

        $created = count($newListingIds);

        if ($created > 0) {
            $userIds = DB::table('board_user')
                ->where('board_key', $this->boardKey)
                ->pluck('user_id')
                ->all();

            if ($userIds !== []) {
                $targets = DB::table('target_profiles')
                    ->whereIn('user_id', $userIds)
                    ->where('is_active', true)
                    ->get(['id', 'user_id']);

                if ($targets->isNotEmpty()) {
                    $pivots = [];
                    foreach ($newListingIds as $listingId) {
                        foreach ($targets as $target) {
                            $pivots[] = [
                                'id' => (string) Str::ulid(),
                                'listing_id' => $listingId,
                                'user_id' => $target->user_id,
                                'target_profile_id' => $target->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    DB::table('listing_user')->insert($pivots);
                }
            }
        }

        Log::info("Scraped {$this->boardKey}: {$total} listings found, {$created} new.");
    }
}
