<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiUsageStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $today = today()->toDateString();
        $weekStart = now()->startOfWeek();

        /** @var object{total_cost: float|null, today_cost: float|null, week_cost: float|null, total_requests: int, today_requests: int, week_requests: int, total_tokens: int|null, today_tokens: int|null, week_tokens: int|null} $stats */
        $stats = AiUsage::query()
            ->where('user_id', auth()->id())
            ->selectRaw('SUM(cost) as total_cost')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN cost ELSE 0 END) as today_cost', [$today])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN cost ELSE 0 END) as week_cost', [$weekStart])
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today_requests', [$today])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as week_requests', [$weekStart])
            ->selectRaw('SUM(prompt_tokens + completion_tokens) as total_tokens')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN prompt_tokens + completion_tokens ELSE 0 END) as today_tokens', [$today])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN prompt_tokens + completion_tokens ELSE 0 END) as week_tokens', [$weekStart])
            ->first();

        return [
            Stat::make('Total Spend', '$'.number_format($stats->total_cost ?? 0, 2))
                ->description('Today: $'.number_format($stats->today_cost ?? 0, 2).' | Week: $'.number_format($stats->week_cost ?? 0, 2))
                ->color('warning'),
            Stat::make('Total Requests', number_format($stats->total_requests))
                ->description("Today: {$stats->today_requests} | Week: {$stats->week_requests}")
                ->color('primary'),
            Stat::make('Total Tokens', AiUsage::formatTokens((int) ($stats->total_tokens ?? 0)))
                ->description('Today: '.AiUsage::formatTokens((int) ($stats->today_tokens ?? 0)).' | Week: '.AiUsage::formatTokens((int) ($stats->week_tokens ?? 0)))
                ->color('success'),
        ];
    }
}
