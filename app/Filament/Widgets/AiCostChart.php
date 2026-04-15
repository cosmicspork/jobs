<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class AiCostChart extends ChartWidget
{
    protected ?string $heading = 'Daily AI Spend (Last 30 Days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    public static function canView(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    protected function getData(): array
    {
        $now = now();
        $days = collect(range(29, 0))->map(fn (int $i) => $now->copy()->subDays($i)->format('Y-m-d'));

        $costs = AiUsage::query()
            ->where('created_at', '>=', $now->copy()->subDays(30)->startOfDay())
            ->selectRaw('DATE(created_at) as date, model, SUM(cost) as total_cost')
            ->groupBy('date', 'model')
            ->get()
            ->groupBy('model');

        $colors = [
            'anthropic/claude-sonnet-4-6' => 'rgb(251, 191, 36)',
            'anthropic/claude-4.6-sonnet-20260217' => 'rgb(251, 191, 36)',
            'anthropic/claude-haiku-4-5' => 'rgb(96, 165, 250)',
            'anthropic/claude-4.5-haiku-20251001' => 'rgb(96, 165, 250)',
        ];

        $datasets = [];

        foreach ($costs as $model => $records) {
            $byDate = $records->keyBy('date');
            $datasets[] = [
                'label' => AiUsage::shortModelName((string) $model),
                'data' => $days->map(fn (string $day) => round((float) ($byDate[$day]->total_cost ?? 0), 4))->all(),
                'borderColor' => $colors[$model] ?? 'rgb(156, 163, 175)',
                'backgroundColor' => $colors[$model] ?? 'rgb(156, 163, 175)',
                'fill' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $days->map(fn (string $d) => Carbon::parse($d)->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
