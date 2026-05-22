<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * An application is owned by exactly one user — only that user may view
     * its generated resume or cover letter, including via the print routes.
     */
    public function view(User $user, Application $application): bool
    {
        return $application->user_id === $user->id;
    }
}
