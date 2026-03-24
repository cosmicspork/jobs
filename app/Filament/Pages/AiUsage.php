<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AiCostChart;
use App\Filament\Widgets\AiPerAgentStats;
use App\Filament\Widgets\AiUsageBreakdownTable;
use App\Filament\Widgets\AiUsageStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AiUsage extends Page
{
    protected string $view = 'filament.pages.ai-usage';

    protected static ?string $title = 'AI Usage';

    protected static ?string $navigationLabel = 'AI Usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 99;

    protected function getHeaderWidgets(): array
    {
        return [
            AiUsageStats::class,
            AiPerAgentStats::class,
            AiCostChart::class,
            AiUsageBreakdownTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
