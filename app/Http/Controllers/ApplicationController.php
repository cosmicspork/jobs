<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Listing;
use App\Models\TargetProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function store(Request $request, Listing $listing): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $target = null;
        if ($request->filled('target_profile_id')) {
            $target = $user->targetProfiles()
                ->where('id', $request->string('target_profile_id'))
                ->where('is_active', true)
                ->first();
        }

        $target ??= $user->bestTargetFor($listing);

        if (! $target instanceof TargetProfile) {
            return back()->with('status', 'Add an active target before generating an application.');
        }

        Application::generateBoth($listing, $user, $target);

        return redirect()->route('filament.admin.resources.listings.view', $listing)
            ->with('status', "Generating application for {$listing->company} ({$target->name})...");
    }
}
