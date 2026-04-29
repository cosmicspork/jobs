<?php

namespace App\Filament\Widgets;

use App\Models\ListingUser;
use App\Relevance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RelevanceByBoardBars extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Relevance by Board (Last 30 Days)';

    /** @var array<string, string> */
    private const BOARD_LABELS = [
        'hn' => 'Hacker News',
        'larajobs' => 'Larajobs',
    ];

    protected function getStats(): array
    {
        $since = now()->subDays(30)->startOfDay();

        $rows = ListingUser::query()
            ->where('listing_user.scored_at', '>=', $since)
            ->join('listings', 'listings.id', '=', 'listing_user.listing_id')
            ->selectRaw('listings.board, listing_user.relevance, COUNT(*) as count')
            ->groupBy('listings.board', 'listing_user.relevance')
            ->get()
            ->groupBy('board');

        $stats = [];

        foreach (self::BOARD_LABELS as $board => $label) {
            $records = $rows->get($board);

            if (! $records) {
                continue;
            }

            $byRelevance = $records->keyBy('relevance');
            $relevant = (int) ($byRelevance[Relevance::Relevant->value]->count ?? 0);
            $maybe = (int) ($byRelevance[Relevance::Maybe->value]->count ?? 0);
            $irrelevant = (int) ($byRelevance[Relevance::Irrelevant->value]->count ?? 0);
            $total = $relevant + $maybe + $irrelevant;

            if ($total === 0) {
                continue;
            }

            $pct = (int) round($relevant / $total * 100);

            $stats[] = Stat::make($label, $pct.'% relevant')
                ->description("{$relevant} relevant · {$maybe} maybe · {$irrelevant} irrelevant")
                ->color($pct >= 30 ? 'success' : ($pct >= 10 ? 'warning' : 'danger'));
        }

        return $stats;
    }
}
