<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('listings:backfill-applied-at {--dry-run : Report how many pivots would be stamped without writing}')]
#[Description('Stamp listing_user.applied_at for listings that already have an Application row, so pre-existing history survives the move off the AI-artifact table.')]
class BackfillAppliedAt extends Command
{
    public function handle(): int
    {
        // applications.applied_at was never written by the app, so created_at
        // is the only signal available. That means "asked the AI for a draft",
        // not "applied" — expect to hand-correct rows afterwards.
        $matching = <<<'SQL'
            select 1 from applications a
            where a.listing_id = listing_user.listing_id
              and a.user_id = listing_user.user_id
        SQL;

        $pending = DB::table('listing_user')
            ->whereNull('applied_at')
            ->whereRaw("exists ({$matching})")
            ->count();

        if ($this->option('dry-run')) {
            $this->info("Would stamp applied_at on {$pending} pivot(s).");

            return self::SUCCESS;
        }

        if ($pending === 0) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        // Idempotent: only ever fills nulls, so re-running is a no-op.
        DB::statement(<<<SQL
            update listing_user
            set applied_at = (
                select min(coalesce(a.applied_at, a.created_at))
                from applications a
                where a.listing_id = listing_user.listing_id
                  and a.user_id = listing_user.user_id
            )
            where applied_at is null
              and exists ({$matching})
        SQL);

        $this->info("Stamped applied_at on {$pending} pivot(s) from existing applications.");
        $this->line('These are inferred from draft-creation time — review the Applied tab and correct any you never sent.');

        return self::SUCCESS;
    }
}
