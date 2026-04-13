<?php

namespace App\Console\Commands;

use App\Jobs\ScoreListing;
use App\Models\ListingUser;
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

        ListingUser::query()
            ->whereNull('scored_at')
            ->with(['listing', 'user'])
            ->chunkById(100, function ($pivots) use (&$count) {
                foreach ($pivots as $pivot) {
                    ScoreListing::dispatch($pivot->listing, $pivot->user);
                    $count++;
                }
            });

        if ($count === 0) {
            $this->info('No unscored listings found.');

            return self::SUCCESS;
        }

        $this->info("Dispatched scoring for {$count} listing-user pairs.");

        return self::SUCCESS;
    }
}
