<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use App\Models\ListingUser;
use App\Models\User;
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

        $cappedUserIds = $this->cappedUserIds($monthStart, $perUserCap);
        $capBlockedCount = $cappedUserIds === []
            ? 0
            : ListingUser::query()
                ->whereNull('scored_at')
                ->whereIn('user_id', $cappedUserIds)
                ->count();
        $allUnscoredAreCapBlocked = $unscoredCount > 0 && $capBlockedCount === $unscoredCount;
        $cappedUserCount = count($cappedUserIds);
        $cappedUserSummary = $cappedUserCount === 1 ? '1 user over cap' : "{$cappedUserCount} users over cap";

        return [
            Stat::make('Unscored Pivots', number_format($unscoredCount))
                ->description($this->unscoredDescription($oldestUnscored, $allUnscoredAreCapBlocked, $capBlockedCount))
                ->color(match (true) {
                    $allUnscoredAreCapBlocked => 'info',
                    $unscoredStale => 'danger',
                    $unscoredCount > 0 => 'warning',
                    default => 'success',
                }),

            Stat::make('Last Successful Score', $lastScoredAt
                    ? $lastScoredAt->diffForHumans(syntax: Carbon::DIFF_RELATIVE_TO_NOW, short: true)
                    : 'never')
                ->description($this->lastScoredDescription($lastScoredAt, $allUnscoredAreCapBlocked, $cappedUserSummary))
                ->color(match (true) {
                    ! $scoringStale => 'success',
                    $allUnscoredAreCapBlocked => 'warning',
                    default => 'danger',
                }),

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

    private function unscoredDescription(?Carbon $oldestUnscored, bool $allCapBlocked, int $capBlockedCount): string
    {
        if ($oldestUnscored === null) {
            return 'all caught up';
        }

        $base = 'oldest '.$oldestUnscored->diffForHumans();

        return $allCapBlocked
            ? "{$base} · paused: {$capBlockedCount} over cap"
            : $base;
    }

    private function lastScoredDescription(?Carbon $lastScoredAt, bool $allCapBlocked, string $cappedUserSummary): string
    {
        $base = $lastScoredAt
            ? $lastScoredAt->format('M j, H:i')
            : 'no scoring runs on record';

        return $allCapBlocked
            ? "{$base} · {$cappedUserSummary}"
            : $base;
    }

    /**
     * @return array<int, int>
     */
    private function cappedUserIds(Carbon $monthStart, float $globalCap): array
    {
        return User::query()
            ->select('users.id', 'users.monthly_ai_cap_usd', DB::raw('COALESCE(SUM(ai_usages.cost), 0) as spend'))
            ->leftJoin('ai_usages', function ($join) use ($monthStart) {
                $join->on('ai_usages.user_id', '=', 'users.id')
                    ->where('ai_usages.created_at', '>=', $monthStart);
            })
            ->groupBy('users.id', 'users.monthly_ai_cap_usd')
            ->get()
            ->filter(fn (User $row): bool => (float) $row->getAttribute('spend') >= (float) ($row->monthly_ai_cap_usd ?? $globalCap))
            ->pluck('id')
            ->all();
    }
}
