<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;

class ApplicationController extends Controller
{
    public function store(Listing $listing): RedirectResponse
    {
        $application = Application::create([
            'listing_id' => $listing->id,
            'status' => 'generating',
        ]);

        Bus::batch([
            new GenerateResume($application),
            new GenerateCoverLetter($application),
        ])->then(function () use ($application) {
            $application->update(['status' => 'ready']);
        })->catch(function () use ($application) {
            $application->update(['status' => 'failed']);
        })->dispatch();

        return redirect()->route('feed')
            ->with('status', "Generating application for {$listing->company}...");
    }
}
