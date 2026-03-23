<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeBoard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;

#[Signature('jobs:scrape')]
#[Description('Scrape all enabled job boards for new listings')]
class ScrapeBoards extends Command
{
    public function handle(): int
    {
        /** @var array<string, array{name: string, scraper: class-string, enabled: bool}> $boards */
        $boards = config('boards');

        $jobs = [];

        foreach ($boards as $key => $board) {
            if (! $board['enabled']) {
                continue;
            }

            $jobs[] = new ScrapeBoard($key, $board['scraper']);
            $this->info("Queued scraper for {$board['name']}");
        }

        if (empty($jobs)) {
            $this->info('No enabled boards found.');

            return self::SUCCESS;
        }

        Bus::batch($jobs)
            ->name('scrape-boards')
            ->then(fn () => Artisan::call('jobs:score'))
            ->dispatch();

        $this->info('Dispatched '.count($jobs).' scrape jobs. Scoring will run automatically after scraping completes.');

        return self::SUCCESS;
    }
}
