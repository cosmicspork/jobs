<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeBoard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:scrape')]
#[Description('Scrape all enabled job boards for new listings')]
class ScrapeBoards extends Command
{
    public function handle(): int
    {
        /** @var array<string, array{name: string, scraper: class-string, enabled: bool}> $boards */
        $boards = config('boards');

        $dispatched = 0;

        foreach ($boards as $key => $board) {
            if (! $board['enabled']) {
                continue;
            }

            ScrapeBoard::dispatch($key, $board['scraper']);
            $this->info("Dispatched scraper for {$board['name']}");
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} scrape jobs.");

        return self::SUCCESS;
    }
}
