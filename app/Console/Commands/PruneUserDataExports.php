<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Signature('exports:prune {--days=7 : Delete exports older than this many days}')]
#[Description('Delete user data exports older than the cutoff to reclaim storage.')]
class PruneUserDataExports extends Command
{
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        foreach (Storage::allFiles('exports') as $path) {
            if (Carbon::createFromTimestamp(Storage::lastModified($path))->lt($cutoff)) {
                Storage::delete($path);
                $deleted++;
            }
        }

        $this->info("Pruned {$deleted} user data export(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
