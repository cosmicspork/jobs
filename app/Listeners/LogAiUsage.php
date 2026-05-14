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
        $agent = $event->prompt->agent;
        $agentName = class_basename($agent);

        $userId = property_exists($agent, 'user') ? $agent->user?->id : null;

        AiUsage::create([
            'user_id' => $userId,
            'agent' => $agentName,
            'provider' => $meta->provider,
            'model' => $meta->model,
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
            'cache_write_tokens' => $usage->cacheWriteInputTokens,
            'cache_read_tokens' => $usage->cacheReadInputTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'cost' => $this->calculateCost($meta->provider, $meta->model, $usage),
        ]);
    }

    private function calculateCost(?string $provider, ?string $model, Usage $usage): float
    {
        $pricing = config("ai.pricing.{$provider}.{$model}");

        if (! $pricing) {
            return 0;
        }

        $inputCost = ($usage->promptTokens / 1_000_000) * $pricing['input'];
        $outputCost = ($usage->completionTokens / 1_000_000) * $pricing['output'];
        $cacheWriteCost = ($usage->cacheWriteInputTokens / 1_000_000) * $pricing['cacheWrite'];
        $cacheReadCost = ($usage->cacheReadInputTokens / 1_000_000) * $pricing['cacheRead'];

        return $inputCost + $outputCost + $cacheWriteCost + $cacheReadCost;
    }
}
