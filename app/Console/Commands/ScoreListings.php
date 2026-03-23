<?php

namespace App\Console\Commands;

use App\Jobs\ScoreListing;
use App\Models\Listing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('jobs:score')]
#[Description('Score all unscored job listings using AI')]
class ScoreListings extends Command
{
    public function handle(): int
    {
        $count = 0;

        Listing::query()
            ->whereNull('scored_at')
            ->chunkById(100, function ($listings) use (&$count) {
                foreach ($listings as $listing) {
                    ScoreListing::dispatch($listing);
                    $count++;
                }
            });

        if ($count === 0) {
            $this->info('No unscored listings found.');

            return self::SUCCESS;
        }

        $this->info("Dispatched scoring for {$count} listings.");

        return self::SUCCESS;
    }
}
