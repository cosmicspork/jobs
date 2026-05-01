<?php

namespace App\Filament\Widgets;

use App\Models\ListingUser;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

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

        // Collapse multi-target pivots to one row per (board, listing) using best-relevance.
        $bestPerListingByBoard = DB::table('listing_user')
            ->join('listings', 'listings.id', '=', 'listing_user.listing_id')
            ->where('listing_user.scored_at', '>=', $since)
            ->select('listings.board', 'listing_user.listing_id')
            ->selectRaw('MIN('.ListingUser::orderByRelevanceSql('listing_user.relevance').') as rank')
            ->groupBy('listings.board', 'listing_user.listing_id');

        $rows = DB::query()
            ->fromSub($bestPerListingByBoard, 'b')
            ->selectRaw('board, rank, COUNT(*) as count')
            ->groupBy('board', 'rank')
            ->get()
            ->groupBy('board');

        $stats = [];

        foreach (self::BOARD_LABELS as $board => $label) {
            $records = $rows->get($board);

            if (! $records) {
                continue;
            }

            $byRank = $records->keyBy('rank');
            $relevant = (int) ($byRank[0]->count ?? 0);
            $maybe = (int) ($byRank[1]->count ?? 0);
            $irrelevant = (int) ($byRank[2]->count ?? 0);
            $total = $relevant + $maybe + $irrelevant;

            if ($total === 0) {
                continue;
            }

            $pct = (int) round($relevant / $total * 100);

            $stats[] = Stat::make($label, $pct.'% relevant')
                ->description("{$relevant} relevant · {$maybe} maybe · {$irrelevant} irrelevant")
                ->color(match (true) {
                    $pct >= 30 => 'success',
                    $pct >= 10 => 'warning',
                    default => 'danger',
                });
        }

        return $stats;
    }
}
