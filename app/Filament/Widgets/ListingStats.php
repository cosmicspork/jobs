<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\ListingUser;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ListingStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $userId = auth()->id();
        $today = today()->toDateString();
        $weekStart = now()->startOfWeek();

        // Collapse multi-target pivots to one row per listing using best-relevance.
        $bestPerListing = DB::table('listing_user')
            ->where('user_id', $userId)
            ->whereNull('dismissed_at')
            ->selectRaw('listing_id')
            ->selectRaw('MIN('.ListingUser::orderByRelevanceSql().') as rank')
            ->selectRaw('MIN(listing_user.created_at) as first_seen')
            ->groupBy('listing_id');

        /** @var object{total: int, today: int, this_week: int, relevant: int, maybe: int, irrelevant: int, unscored: int} $listings */
        $listings = DB::query()
            ->fromSub($bestPerListing, 'b')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN DATE(first_seen) = ? THEN 1 ELSE 0 END) as today', [$today])
            ->selectRaw('SUM(CASE WHEN first_seen >= ? THEN 1 ELSE 0 END) as this_week', [$weekStart])
            ->selectRaw('SUM(CASE WHEN rank = 0 THEN 1 ELSE 0 END) as relevant')
            ->selectRaw('SUM(CASE WHEN rank = 1 THEN 1 ELSE 0 END) as maybe')
            ->selectRaw('SUM(CASE WHEN rank = 2 THEN 1 ELSE 0 END) as irrelevant')
            ->selectRaw('SUM(CASE WHEN rank = 99 THEN 1 ELSE 0 END) as unscored')
            ->first();

        $applications = Application::where('user_id', $userId)->count();

        return [
            Stat::make('Total Listings', number_format($listings->total))
                ->description("Today: {$listings->today} | Week: {$listings->this_week}")
                ->color('primary'),
            Stat::make('Relevant', $listings->relevant)
                ->description("Maybe: {$listings->maybe} | Irrelevant: {$listings->irrelevant}")
                ->color('success'),
            Stat::make('Unscored', $listings->unscored)
                ->color($listings->unscored > 0 ? 'warning' : 'gray'),
            Stat::make('Applications', $applications)
                ->color('primary'),
        ];
    }
}
