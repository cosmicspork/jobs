<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ListingsPerDayChart;
use App\Filament\Widgets\ListingStats;
use App\Filament\Widgets\RelevanceByBoardChart;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dashboard extends Page
{
    protected string $view = 'filament.pages.dashboard';

    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?int $navigationSort = -1;

    protected function getHeaderWidgets(): array
    {
        return [
            ListingStats::class,
            ListingsPerDayChart::class,
            RelevanceByBoardChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
