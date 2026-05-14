<?php

namespace App\Jobs;

use App\Ai\Agents\JobScorerAgent;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\TargetProfile;
use App\Relevance;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;

class ScoreListing implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public Listing $listing,
        public TargetProfile $target,
    ) {}

    public function handle(): void
    {
        $user = $this->target->user;

        if ($user->isOverAiCap()) {
            return;
        }

        $provider = config('ai.agents.scorer.provider');

        if (self::providerFrozenUntil($provider)) {
            return;
        }

        $listingJson = json_encode($this->listing->toAgentPayload(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        try {
            $response = (new JobScorerAgent($user, $this->target))->prompt(
                "Score this job listing:\n```json\n{$listingJson}\n```"
            );
        } catch (AiException $e) {
            if ($until = self::extractProviderUsageLimit($e->getMessage())) {
                self::freezeProvider($provider, $until);

                Log::warning("AI provider [{$provider}] hit usage limit; freezing scoring until {$until->toIso8601String()}.");

                $this->fail($e);

                return;
            }

            throw $e;
        }

        if (! isset($response['relevance'])) {
            throw new \RuntimeException(
                "AI response missing required 'relevance' key for listing {$this->listing->id}."
            );
        }

        $relevance = Relevance::from($response['relevance']);

        ListingUser::query()
            ->where('listing_id', $this->listing->id)
            ->where('target_profile_id', $this->target->id)
            ->update([
                'relevance' => $relevance,
                'score_data' => [
                    'matched_skills' => $response['matched_skills'],
                    'gaps' => $response['gaps'],
                    'reasoning' => $response['reasoning'],
                    'posting_quality_signals' => $response['posting_quality_signals'] ?? [],
                ],
                'scored_at' => now(),
            ]);

        Log::info("Scored listing {$this->listing->id} for target {$this->target->id} ({$this->target->name}): {$relevance->value}");
    }

    public static function providerFrozenUntil(string $provider): ?CarbonImmutable
    {
        $iso = Cache::get(self::frozenCacheKey($provider));

        return $iso ? CarbonImmutable::parse($iso) : null;
    }

    public static function freezeProvider(string $provider, CarbonImmutable $until): void
    {
        Cache::put(
            self::frozenCacheKey($provider),
            $until->toIso8601String(),
            $until,
        );
    }

    private static function frozenCacheKey(string $provider): string
    {
        return "ai_provider_frozen_until:{$provider}";
    }

    private static function extractProviderUsageLimit(string $message): ?CarbonImmutable
    {
        if (! str_contains($message, 'usage limit')) {
            return null;
        }

        if (preg_match('/regain access on (\d{4}-\d{2}-\d{2})(?: at (\d{2}:\d{2}) UTC)?/i', $message, $m)) {
            $time = $m[2] ?? '00:00';

            return CarbonImmutable::parse("{$m[1]} {$time}", 'UTC');
        }

        return CarbonImmutable::now()->addHours(6);
    }
}
