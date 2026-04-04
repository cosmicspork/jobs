<?php

namespace App\Filament\Widgets;

use App\Models\Application;
use App\Models\Listing;
use App\Relevance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ListingStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $today = today()->toDateString();
        $weekStart = now()->startOfWeek();

        /** @var object{total: int, today: int, this_week: int, relevant: int, maybe: int, irrelevant: int, unscored: int} $listings */
        $listings = Listing::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN DATE(scraped_at) = ? THEN 1 ELSE 0 END) as today', [$today])
            ->selectRaw('SUM(CASE WHEN scraped_at >= ? THEN 1 ELSE 0 END) as this_week', [$weekStart])
            ->selectRaw('SUM(CASE WHEN relevance = ? THEN 1 ELSE 0 END) as relevant', [Relevance::Relevant->value])
            ->selectRaw('SUM(CASE WHEN relevance = ? THEN 1 ELSE 0 END) as maybe', [Relevance::Maybe->value])
            ->selectRaw('SUM(CASE WHEN relevance = ? THEN 1 ELSE 0 END) as irrelevant', [Relevance::Irrelevant->value])
            ->selectRaw('SUM(CASE WHEN scored_at IS NULL THEN 1 ELSE 0 END) as unscored')
            ->first();

        $applications = Application::count();

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
