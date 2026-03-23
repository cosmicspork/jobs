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

class GenerateCoverLetter implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public Application $application) {}

    public function handle(): void
    {
        $listing = $this->application->listing;

        $response = (new CoverLetterAgent)->prompt(
            "Write a cover letter for this job posting (listing_id: {$listing->id})."
        );

        $pdf = Pdf::loadView('cover-letter.base', [
            'profile' => config('profile'),
            'subjectLine' => $response['subject_line'],
            'body' => $response['body'],
            'listing' => $listing,
        ]);

        $path = "cover-letters/{$this->application->id}.pdf";
        Storage::put($path, $pdf->output());

        $this->application->update(['cover_letter_path' => $path]);

        Log::info("Generated cover letter for application {$this->application->id}");
    }
}
