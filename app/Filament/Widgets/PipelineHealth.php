<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use App\Models\ListingUser;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PipelineHealth extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Pipeline Health';

    /** Unscored older than this means the score gate is silently dropping work. */
    private const UNSCORED_STALE_AFTER_HOURS = 1;

    /** No successful score in this long means scoring is dead. */
    private const SCORING_STALE_AFTER_HOURS = 25;

    public static function canView(): bool
    {
        return auth()->user()->is_admin;
    }

    protected function getStats(): array
    {
        /** @var object{count: int, oldest: string|null} $unscored */
        $unscored = ListingUser::query()
            ->whereNull('scored_at')
            ->selectRaw('COUNT(*) as count, MIN(created_at) as oldest')
            ->first();

        $unscoredCount = (int) $unscored->count;
        $oldestUnscored = $unscored->oldest ? Carbon::parse($unscored->oldest) : null;
        $oldestUnscoredHours = $oldestUnscored?->diffInHours(now()) ?? 0;
        $unscoredStale = $unscoredCount > 0 && $oldestUnscoredHours >= self::UNSCORED_STALE_AFTER_HOURS;

        $lastScoredAt = ListingUser::query()->max('scored_at');
        $lastScoredAt = $lastScoredAt ? Carbon::parse($lastScoredAt) : null;
        $scoringStale = $lastScoredAt === null
            || $lastScoredAt->diffInHours(now()) >= self::SCORING_STALE_AFTER_HOURS;

        $failedJobs = (int) DB::table('failed_jobs')->count();

        $monthStart = now()->startOfMonth();
        $monthSpend = (float) AiUsage::query()
            ->where('created_at', '>=', $monthStart)
            ->sum('cost');
        $perUserCap = (float) config('scoring.monthly_cap_usd');

        return [
            Stat::make('Unscored Pivots', number_format($unscoredCount))
                ->description($oldestUnscored
                    ? 'oldest '.$oldestUnscored->diffForHumans()
                    : 'all caught up')
                ->color(match (true) {
                    $unscoredStale => 'danger',
                    $unscoredCount > 0 => 'warning',
                    default => 'success',
                }),

            Stat::make('Last Successful Score', $lastScoredAt
                    ? $lastScoredAt->diffForHumans(syntax: Carbon::DIFF_RELATIVE_TO_NOW, short: true)
                    : 'never')
                ->description($lastScoredAt
                    ? $lastScoredAt->format('M j, H:i')
                    : 'no scoring runs on record')
                ->color($scoringStale ? 'danger' : 'success'),

            Stat::make('Failed Jobs', number_format($failedJobs))
                ->description($failedJobs > 0 ? 'check failed_jobs table' : 'queue is clean')
                ->color($failedJobs > 0 ? 'warning' : 'success'),

            Stat::make('AI Spend (this month)', '$'.number_format($monthSpend, 2))
                ->description($perUserCap > 0
                    ? '$'.number_format($perUserCap, 2).' per-user cap'
                    : 'no cap configured')
                ->color('primary'),
        ];
    }
}
