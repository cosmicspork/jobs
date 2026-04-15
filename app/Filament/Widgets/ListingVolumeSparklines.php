<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

class ListingVolumeSparklines extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Listings Scraped (Last 30 Days)';

    /** @var array<string, array{label: string, color: string}> */
    private const BOARDS = [
        'hn' => ['label' => 'Hacker News', 'color' => 'warning'],
        'larajobs' => ['label' => 'Larajobs', 'color' => 'info'],
    ];

    protected function getStats(): array
    {
        $since = now()->subDays(30)->startOfDay();

        /** @var Collection<int, object{board: string, date: string, count: int}> $rows */
        $rows = Listing::query()
            ->where('scraped_at', '>=', $since)
            ->selectRaw('board, DATE(scraped_at) as date, COUNT(*) as count')
            ->groupBy('board', 'date')
            ->get();

        $grouped = $rows->groupBy('board');

        $days = collect(range(29, 0))
            ->map(fn (int $i): string => now()->subDays($i)->format('Y-m-d'));

        $stats = [];

        foreach (self::BOARDS as $board => $meta) {
            $byDate = ($grouped->get($board) ?? collect())->keyBy('date');
            $counts = $days->map(fn (string $day): int => (int) ($byDate[$day]->count ?? 0))->all();

            $total = array_sum($counts);
            $today = (int) end($counts);

            $stats[] = Stat::make($meta['label'], number_format($total))
                ->description("{$today} today · peak ".max($counts))
                ->chart($counts)
                ->color($meta['color']);
        }

        return $stats;
    }
}
