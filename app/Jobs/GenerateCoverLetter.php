<?php

namespace App\Jobs;

use App\Ai\Agents\CoverLetterAgent;
use App\Ai\ProviderFreeze;
use App\Jobs\Concerns\FreezesAiProvider;
use App\Models\Application;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\AiException;

class GenerateCoverLetter implements ShouldQueue
{
    use Batchable, FreezesAiProvider, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 30;

    public function __construct(public Application $application) {}

    public function handle(): void
    {
        $listing = $this->application->listing;
        $user = $this->application->user;
        $target = $this->application->targetProfile;

        $provider = config('ai.agents.cover_letter.provider');

        if (ProviderFreeze::providerFrozenUntil($provider)) {
            return;
        }

        $listingJson = json_encode($listing->toAgentPayload(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $extra = trim((string) $this->application->extra_instructions);

        $userMessage = "Write a cover letter for this job posting:\n```json\n{$listingJson}\n```";

        if ($extra !== '') {
            $userMessage .= "\n\nADDITIONAL INSTRUCTIONS FROM THE CANDIDATE:\n{$extra}";
        }

        $agent = new CoverLetterAgent($user, $target);

        try {
            $response = $agent->prompt(
                $userMessage,
                provider: $agent->providers() ?: null,
            );
        } catch (AiException $e) {
            if ($this->failIfUsageLimited($provider, $e)) {
                return;
            }

            throw $e;
        }

        $this->application->update([
            'cover_letter_content' => [
                'subject_line' => $response['subject_line'],
                'body' => $response['body'],
                'word_count' => $response['word_count'] ?? null,
                'posting_detail_referenced' => $response['posting_detail_referenced'] ?? null,
            ],
        ]);

        Log::info("Generated cover letter content for application {$this->application->id}");
    }
}
