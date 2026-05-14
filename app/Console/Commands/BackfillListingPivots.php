<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('listings:backfill-pivots {--user= : User id to backfill pivots for} {--since= : Only listings scraped on or after this date (YYYY-MM-DD)} {--score-now : Run jobs:score immediately after backfilling}')]
#[Description('Backfill listing_user pivots for one user against listings scraped since a date. Pivots are pre-marked digested so they bypass the next daily email; scoring happens on the next jobs:score tick (or now with --score-now).')]
class BackfillListingPivots extends Command
{
    public function handle(): int
    {
        $userOption = $this->option('user');
        $sinceOption = $this->option('since');

        if (! filled($userOption) || ! filled($sinceOption)) {
            $this->error('Both --user and --since are required.');

            return self::FAILURE;
        }

        try {
            $user = User::query()->findOrFail((int) $userOption);
        } catch (ModelNotFoundException) {
            $this->error("No user with id {$userOption}.");

            return self::FAILURE;
        }

        try {
            $since = Carbon::parse((string) $sinceOption)->startOfDay();
        } catch (InvalidFormatException) {
            $this->error("Could not parse --since={$sinceOption} as a date.");

            return self::FAILURE;
        }

        $targets = $user->targetProfiles()->where('is_active', true)->get();

        if ($targets->isEmpty()) {
            $this->warn("User {$user->id} has no active targets — nothing to backfill.");

            return self::SUCCESS;
        }

        $now = now();
        $created = 0;
        $skipped = 0;
        $listings = 0;

        Listing::query()
            ->where('scraped_at', '>=', $since)
            ->cursor()
            ->each(function (Listing $listing) use ($targets, $now, &$created, &$skipped, &$listings): void {
                $listings++;

                foreach ($targets as $target) {
                    $inserted = DB::table('listing_user')->insertOrIgnore([
                        'id' => (string) Str::ulid(),
                        'listing_id' => $listing->id,
                        'user_id' => $target->user_id,
                        'target_profile_id' => $target->id,
                        'scored_at' => null,
                        'digested_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $inserted === 1 ? $created++ : $skipped++;
                }
            });

        $this->info(sprintf(
            'Backfilled %d pivot(s) across %d listing(s) × %d target(s); %d skipped (already existed).',
            $created,
            $listings,
            $targets->count(),
            $skipped,
        ));

        if ($this->option('score-now')) {
            $this->info('Running jobs:score…');
            Artisan::call('jobs:score', [], $this->getOutput());
        } else {
            $this->line('Run `php artisan jobs:score` (or wait for the hourly tick) to populate scores.');
        }

        return self::SUCCESS;
    }
}
