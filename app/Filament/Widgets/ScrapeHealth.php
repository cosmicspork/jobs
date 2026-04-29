<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ScrapeHealth extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Scrape Health';

    /** Anything older than this means the hourly scrape stopped firing. */
    private const STALE_AFTER_MINUTES = 120;

    public static function canView(): bool
    {
        return auth()->user()?->is_admin ?? false;
    }

    protected function getStats(): array
    {
        /** @var array<string, array{name: string}> $boards */
        $boards = config('boards', []);

        $today = today()->toDateString();
        $weekStart = now()->startOfWeek();
        $sevenDays = now()->subDays(7);

        /** @var array<int, object{board: string, last_scraped: string|null, today: int, this_week: int, last_7d: int}> $rows */
        $rows = Listing::query()
            ->selectRaw('board')
            ->selectRaw('MAX(scraped_at) as last_scraped')
            ->selectRaw('SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today', [$today])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as this_week', [$weekStart])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as last_7d', [$sevenDays])
            ->groupBy('board')
            ->get()
            ->keyBy('board');

        $stats = [];

        foreach ($boards as $key => $meta) {
            $row = $rows->get($key);
            $lastScraped = $row?->last_scraped ? Carbon::parse($row->last_scraped) : null;
            $minutesSince = $lastScraped?->diffInMinutes(now()) ?? null;

            $isStale = $minutesSince === null || $minutesSince > self::STALE_AFTER_MINUTES;

            $value = $lastScraped
                ? $lastScraped->diffForHumans(syntax: Carbon::DIFF_RELATIVE_TO_NOW, short: true)
                : 'never';

            $stats[] = Stat::make($meta['name'] ?? $key, $value)
                ->description(sprintf(
                    '+%d today · +%d this week · +%d last 7d',
                    (int) ($row->today ?? 0),
                    (int) ($row->this_week ?? 0),
                    (int) ($row->last_7d ?? 0),
                ))
                ->color($isStale ? 'danger' : 'success');
        }

        return $stats;
    }
}
