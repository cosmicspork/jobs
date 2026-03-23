<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;

class ApplicationController extends Controller
{
    public function store(Listing $listing): RedirectResponse
    {
        Application::generate($listing);

        return redirect()->route('filament.admin.resources.listings.view', $listing)
            ->with('status', "Generating application for {$listing->company}...");
    }
}
