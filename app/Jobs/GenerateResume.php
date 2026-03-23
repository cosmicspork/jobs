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

class GenerateResume implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public Application $application) {}

    public function handle(): void
    {
        $listing = $this->application->listing;

        $response = (new ResumeTailorAgent)->prompt(
            "Tailor my resume for this job posting (listing_id: {$listing->id})."
        );

        $pdf = Pdf::loadView('resume.base', [
            'profile' => config('profile'),
            'summary' => $response['summary'],
            'skills' => $response['skills'],
            'highlights' => $response['experience_highlights'],
            'listing' => $listing,
        ]);

        $path = "resumes/{$this->application->id}.pdf";
        Storage::put($path, $pdf->output());

        $this->application->update(['resume_path' => $path]);

        Log::info("Generated resume for application {$this->application->id}");
    }
}
