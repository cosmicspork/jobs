<?php

namespace App\Console\Commands;

use App\Models\AiUsage;
use Illuminate\Console\Command;

class BackfillAiUsageCosts extends Command
{
    protected $signature = 'ai-usage:backfill-costs';

    protected $description = 'Recalculate costs for AI usage records with zero cost';

    public function handle(): int
    {
        $updated = 0;

        AiUsage::query()
            ->where('cost', 0)
            ->whereNotNull('model')
            ->whereNotNull('provider')
            ->chunkById(100, function ($records) use (&$updated): void {
                foreach ($records as $record) {
                    $pricing = config("ai.pricing.{$record->provider}.{$record->model}");

                    if (! $pricing) {
                        continue;
                    }

                    $cost = ($record->prompt_tokens / 1_000_000) * $pricing['input']
                        + ($record->completion_tokens / 1_000_000) * $pricing['output']
                        + ($record->cache_write_tokens / 1_000_000) * $pricing['cacheWrite']
                        + ($record->cache_read_tokens / 1_000_000) * $pricing['cacheRead'];

                    $record->update(['cost' => $cost]);
                    $updated++;
                }
            });

        $this->info("Updated {$updated} records.");

        return self::SUCCESS;
    }
}
