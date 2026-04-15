<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AiCostChart;
use App\Filament\Widgets\AiModelAgentBreakdown;
use App\Filament\Widgets\AiPerUserBreakdown;
use App\Filament\Widgets\AiUsageSummaryStats;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class AdminAiUsage extends Page
{
    protected string $view = 'filament.pages.admin-ai-usage';

    protected static ?string $title = 'AI Usage';

    protected static ?string $navigationLabel = 'AI Usage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?int $navigationSort = 101;

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    public static function canAccess(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AiUsageSummaryStats::class,
            AiCostChart::class,
            AiPerUserBreakdown::class,
            AiModelAgentBreakdown::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
