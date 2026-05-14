<?php

namespace App\Jobs;

use App\Ai\Agents\CoverLetterAgent;
use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateCoverLetter implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public Application $application) {}

    public function handle(): void
    {
        $listing = $this->application->listing;
        $user = $this->application->user;
        $target = $this->application->targetProfile;
        $profile = $user->getProfileData();

        $listingJson = json_encode($listing->toAgentPayload(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        $agent = new CoverLetterAgent($user, $target);

        $response = $agent->prompt(
            "Write a cover letter for this job posting:\n```json\n{$listingJson}\n```",
            provider: $agent->providers() ?: null,
        );

        $pdf = Pdf::loadView('cover-letter.base', [
            'profile' => $profile,
            'target' => $target,
            'subjectLine' => $response['subject_line'],
            'body' => $response['body'],
            'listing' => $listing,
        ]);

        $slug = Str::slug($profile['name'].'_CoverLetter');
        $path = "cover-letters/{$slug}_{$this->application->id}.pdf";
        Storage::put($path, $pdf->output());

        $this->application->update(['cover_letter_path' => $path]);

        Log::info("Generated cover letter for application {$this->application->id}");
    }
}
