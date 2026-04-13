<?php

namespace App\Filament\Widgets;

use App\Models\AiUsage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiPerAgentStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Average Per Request';

    protected function getStats(): array
    {
        $agents = AiUsage::query()
            ->where('user_id', auth()->id())
            ->selectRaw('agent, COUNT(*) as requests, AVG(prompt_tokens + completion_tokens) as avg_tokens, AVG(cost) as avg_cost')
            ->groupBy('agent')
            ->get()
            ->keyBy('agent');

        return [
            $this->agentStat('Job Scoring', $agents->get('JobScorerAgent')),
            $this->agentStat('Resume', $agents->get('ResumeTailorAgent')),
            $this->agentStat('Cover Letter', $agents->get('CoverLetterAgent')),
        ];
    }

    private function agentStat(string $label, ?object $data): Stat
    {
        if (! $data) {
            return Stat::make($label, 'No data');
        }

        return Stat::make($label, '$'.number_format($data->avg_cost, 4).'/req')
            ->description(AiUsage::formatTokens((int) $data->avg_tokens)." avg tokens | {$data->requests} requests");
    }
}
