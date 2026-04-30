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
        $existingUrls = array_flip(
            Listing::query()
                ->whereIn('url', array_column($rows, 'url'))
                ->pluck('url')
                ->all()
        );

        /** @var array<int, array<string, mixed>> $payload */
        $payload = [];
        /** @var array<int, string> $newListingIds */
        $newListingIds = [];

        foreach ($rows as $data) {
            $id = (string) Str::ulid();

            if (! isset($existingUrls[$data['url']])) {
                $newListingIds[] = $id;
            }

            $payload[] = [
                'id' => $id,
                'title' => $data['title'],
                'company' => $data['company'],
                'url' => $data['url'],
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
        }

        Listing::query()->upsert($payload, ['url'], [
            'title', 'company', 'description', 'salary_min', 'salary_max',
            'remote', 'board', 'raw_data', 'scraped_at', 'updated_at',
        ]);

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
