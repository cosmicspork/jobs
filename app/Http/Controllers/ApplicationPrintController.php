<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ApplicationPrintController extends Controller
{
    public function resume(Application $application): View
    {
        Gate::authorize('view', $application);

        return view('print.resume', [
            'application' => $application,
            'content' => $application->resume_content ?? [],
            'profile' => $application->user->getProfileData(),
            'listing' => $application->listing,
        ]);
    }

    public function coverLetter(Application $application): View
    {
        Gate::authorize('view', $application);

        return view('print.cover-letter', [
            'application' => $application,
            'content' => $application->cover_letter_content ?? [],
            'profile' => $application->user->getProfileData(),
            'listing' => $application->listing,
        ]);
    }
}
