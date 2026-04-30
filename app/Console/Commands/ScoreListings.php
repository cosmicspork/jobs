<?php

namespace App\Console\Commands;

use App\Jobs\ScoreListing;
use App\Mail\UserCapReached;
use App\Models\AiUsage;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use App\Services\ListingFilter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

#[Signature('jobs:score')]
#[Description('Score all unscored job listings using AI')]
class ScoreListings extends Command
{
    public function handle(ListingFilter $filter): int
    {
        $cap = (float) config('scoring.monthly_cap_usd');
        $monthStart = now()->startOfMonth();

        /** @var array<int, float> $spendByUser */
        $spendByUser = [];
        /** @var array<int, User> $cappedUsers */
        $cappedUsers = [];

        $counts = ['dispatched' => 0, 'filtered' => 0, 'skippedIncomplete' => 0, 'skippedCap' => 0, 'skippedInactiveTarget' => 0];

        ListingUser::query()
            ->whereNull('scored_at')
            ->with(['listing', 'user', 'targetProfile'])
            ->chunkById(100, function ($pivots) use (&$counts, &$spendByUser, &$cappedUsers, $filter, $cap, $monthStart) {
                foreach ($pivots as $pivot) {
                    $this->processPivot($pivot, $filter, $cap, $monthStart, $counts, $spendByUser, $cappedUsers);
                }
            });

        foreach ($cappedUsers as $user) {
            $this->notifyAdminCapReached($user, $spendByUser[$user->id], $cap);
        }

        if (array_sum($counts) === 0) {
            $this->info('No unscored listings found.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Dispatched: %d | Filtered: %d | Skipped (incomplete profile): %d | Skipped (inactive target): %d | Skipped (cap): %d',
            $counts['dispatched'], $counts['filtered'], $counts['skippedIncomplete'], $counts['skippedInactiveTarget'], $counts['skippedCap']
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, float>  $spendByUser
     * @param  array<int, User>  $cappedUsers
     */
    protected function processPivot(
        ListingUser $pivot,
        ListingFilter $filter,
        float $cap,
        Carbon $monthStart,
        array &$counts,
        array &$spendByUser,
        array &$cappedUsers,
    ): void {
        $user = $pivot->user;
        $target = $pivot->targetProfile;

        if ($user === null || ! $target?->is_active) {
            $counts['skippedInactiveTarget']++;

            return;
        }

        if (! $user->hasMinimumProfile()) {
            $counts['skippedIncomplete']++;

            Log::warning('Skipping scoring for user with incomplete profile', [
                'user_id' => $user->id,
                'email' => $user->email,
                'pivot_id' => $pivot->id,
            ]);

            return;
        }

        $spendByUser[$user->id] ??= (float) AiUsage::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $monthStart)
            ->sum('cost');

        if ($spendByUser[$user->id] >= $cap) {
            $counts['skippedCap']++;
            $cappedUsers[$user->id] = $user;

            return;
        }

        if ($reason = $filter->reasonToSkip($pivot->listing, $target)) {
            $pivot->update([
                'relevance' => Relevance::Irrelevant,
                'score_data' => ['filtered' => true, 'filter_reason' => $reason->value],
                'scored_at' => now(),
            ]);
            $counts['filtered']++;

            return;
        }

        ScoreListing::dispatch($pivot->listing, $target);
        $counts['dispatched']++;
    }

    protected function notifyAdminCapReached(User $user, float $spend, float $cap): void
    {
        $cacheKey = "ai_cap_alert:{$user->id}:".now()->format('Y-m');
        $adminEmail = config('scoring.admin_alert_email');

        if (Cache::has($cacheKey) || ! $adminEmail) {
            return;
        }

        Mail::to($adminEmail)->send(new UserCapReached($user, $spend, $cap));
        Cache::put($cacheKey, true, now()->addMonth());
    }
}
