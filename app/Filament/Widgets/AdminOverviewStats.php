<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $today = today()->toDateString();
        $weekStart = now()->startOfWeek();

        /** @var object{total: int, today: int, this_week: int} $listings */
        $listings = Listing::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN DATE(scraped_at) = ? THEN 1 ELSE 0 END) as today', [$today])
            ->selectRaw('SUM(CASE WHEN scraped_at >= ? THEN 1 ELSE 0 END) as this_week', [$weekStart])
            ->first();

        /** @var object{total: int, scored: int} $coverage */
        $coverage = ListingUser::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN scored_at IS NOT NULL THEN 1 ELSE 0 END) as scored')
            ->first();

        $coveragePct = $coverage->total > 0
            ? (int) round($coverage->scored / $coverage->total * 100)
            : 0;
        $unscored = max($coverage->total - $coverage->scored, 0);

        $totalUsers = User::count();
        $activeUsers = AiUsage::query()
            ->where('created_at', '>=', $weekStart)
            ->distinct('user_id')
            ->count('user_id');

        $totalCost = (float) AiUsage::sum('cost');
        $todayCost = (float) AiUsage::whereDate('created_at', $today)->sum('cost');

        return [
            Stat::make('Users', number_format($totalUsers))
                ->description("{$activeUsers} active this week")
                ->color('primary'),
            Stat::make('Listings', number_format($listings->total))
                ->description("+{$listings->today} today · +{$listings->this_week} this week")
                ->color('primary'),
            Stat::make('AI Spend', '$'.number_format($totalCost, 2))
                ->description('Today: $'.number_format($todayCost, 2))
                ->color('warning'),
            Stat::make('Scored Coverage', $coveragePct.'%')
                ->description(number_format($unscored).' unscored')
                ->color($coveragePct >= 80 ? 'success' : ($coveragePct >= 50 ? 'primary' : 'warning')),
        ];
    }
}
