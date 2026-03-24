<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ListingsPerDayChart extends ChartWidget
{
    protected ?string $heading = 'Listings Scraped (Last 30 Days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $now = now();
        $days = collect(range(29, 0))->map(fn (int $i) => $now->copy()->subDays($i)->format('Y-m-d'));

        $listings = Listing::query()
            ->where('scraped_at', '>=', $now->copy()->subDays(30)->startOfDay())
            ->selectRaw('DATE(scraped_at) as date, board, COUNT(*) as count')
            ->groupBy('date', 'board')
            ->get()
            ->groupBy('board');

        $colors = [
            'hn' => 'rgb(251, 146, 60)',
            'larajobs' => 'rgb(96, 165, 250)',
        ];

        $datasets = [];

        foreach ($listings as $board => $records) {
            $byDate = $records->keyBy('date');
            $datasets[] = [
                'label' => $board === 'hn' ? 'Hacker News' : 'Larajobs',
                'data' => $days->map(fn (string $day) => (int) ($byDate[$day]->count ?? 0))->all(),
                'borderColor' => $colors[$board] ?? 'rgb(156, 163, 175)',
                'backgroundColor' => $colors[$board] ?? 'rgb(156, 163, 175)',
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
