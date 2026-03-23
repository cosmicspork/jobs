<?php

namespace App\Jobs;

use App\Models\Listing;
use App\Services\Scrapers\ScraperInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeBoard implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $boardKey,
        public string $scraperClass,
    ) {}

    public function handle(): void
    {
        /** @var ScraperInterface $scraper */
        $scraper = app($this->scraperClass);

        $listings = $scraper->scrape();

        $created = 0;

        foreach ($listings as $data) {
            $listing = Listing::query()->updateOrCreate(
                ['url' => $data['url']],
                [
                    'title' => $data['title'],
                    'company' => $data['company'],
                    'description' => $data['description'],
                    'salary_min' => $data['salary_min'],
                    'salary_max' => $data['salary_max'],
                    'remote' => $data['remote'],
                    'board' => $this->boardKey,
                    'raw_data' => $data['raw_data'],
                    'scraped_at' => now(),
                ],
            );

            if ($listing->wasRecentlyCreated) {
                $created++;
            }
        }

        Log::info("Scraped {$this->boardKey}: ".count($listings)." listings found, {$created} new.");
    }
}
