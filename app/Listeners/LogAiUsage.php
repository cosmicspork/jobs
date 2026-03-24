<?php

namespace App\Listeners;

use App\Models\AiUsage;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Responses\Data\Usage;

class LogAiUsage
{
    public function handle(AgentPrompted $event): void
    {
        $usage = $event->response->usage;
        $meta = $event->response->meta;
        $agent = class_basename($event->prompt->agent);

        AiUsage::create([
            'agent' => $agent,
            'provider' => $meta->provider,
            'model' => $meta->model,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'cache_write_tokens' => $usage->cacheWriteInputTokens,
            'cache_read_tokens' => $usage->cacheReadInputTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'cost' => $this->calculateCost($meta->model, $usage),
        ]);
    }

    private function calculateCost(?string $model, Usage $usage): float
    {
        $pricing = AiUsage::PRICING[$model] ?? null;

        if (! $pricing) {
            return 0;
        }

        $inputCost = ($usage->promptTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($usage->completionTokens / 1_000_000) * $pricing['output'];
        $cacheWriteCost = (($usage->cacheWriteInputTokens ?? 0) / 1_000_000) * $pricing['cacheWrite'];
        $cacheReadCost = (($usage->cacheReadInputTokens ?? 0) / 1_000_000) * $pricing['cacheRead'];

        return $inputCost + $outputCost + $cacheWriteCost + $cacheReadCost;
    }
}
