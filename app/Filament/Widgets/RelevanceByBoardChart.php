<?php

namespace App\Filament\Widgets;

use App\Models\Listing;
use App\Relevance;
use Filament\Widgets\ChartWidget;

class RelevanceByBoardChart extends ChartWidget
{
    protected ?string $heading = 'Relevance Rate by Board';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '250px';

    protected ?string $description = 'Percentage of scored listings by relevance tier';

    protected function getData(): array
    {
        $boards = Listing::query()
            ->whereNotNull('scored_at')
            ->selectRaw('board, relevance, COUNT(*) as count')
            ->groupBy('board', 'relevance')
            ->get()
            ->groupBy('board');

        $labels = [];
        $relevantData = [];
        $maybeData = [];
        $irrelevantData = [];

        foreach ($boards as $board => $records) {
            $byRelevance = $records->keyBy(fn ($r) => $r->relevance->value);
            $total = $records->sum('count');

            $labels[] = $board === 'hn' ? 'Hacker News' : 'Larajobs';
            $relevantData[] = round(($byRelevance[Relevance::Relevant->value]->count ?? 0) / $total * 100, 1);
            $maybeData[] = round(($byRelevance[Relevance::Maybe->value]->count ?? 0) / $total * 100, 1);
            $irrelevantData[] = round(($byRelevance[Relevance::Irrelevant->value]->count ?? 0) / $total * 100, 1);
        }

        return [
            'datasets' => [
                ['label' => 'Relevant', 'data' => $relevantData, 'backgroundColor' => 'rgb(34, 197, 94)'],
                ['label' => 'Maybe', 'data' => $maybeData, 'backgroundColor' => 'rgb(251, 191, 36)'],
                ['label' => 'Irrelevant', 'data' => $irrelevantData, 'backgroundColor' => 'rgb(239, 68, 68)'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['max' => 100],
            ],
        ];
    }
}
