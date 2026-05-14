<?php

namespace App\Jobs;

use App\Ai\Agents\ResumeTailorAgent;
use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenerateResume implements ShouldQueue
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

        $agent = new ResumeTailorAgent($user, $target);

        $response = $agent->prompt(
            "Tailor my resume for this job posting:\n```json\n{$listingJson}\n```",
            provider: $agent->providers() ?: null,
        );

        $pdf = Pdf::loadView('resume.base', [
            'profile' => $profile,
            'target' => $target,
            'summary' => $response['summary'],
            'skills' => $response['skills'],
            'experience' => $response['experience'],
            'listing' => $listing,
        ]);

        $slug = Str::slug($profile['name'].'_Resume');
        $path = "resumes/{$slug}_{$this->application->id}.pdf";
        Storage::put($path, $pdf->output());

        $this->application->update(['resume_path' => $path]);

        Log::info("Generated resume for application {$this->application->id}");
    }
}
