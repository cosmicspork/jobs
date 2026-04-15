<?php

namespace App\Services;

use App\FilterReason;
use App\Models\Listing;
use App\Models\User;

class ListingFilter
{
    public function reasonToSkip(Listing $listing, User $user): ?FilterReason
    {
        $prefs = $user->preferences ?? [];

        if (($prefs['remote'] ?? false) === true && $listing->remote === false) {
            return FilterReason::NotRemote;
        }

        $userMin = $prefs['salary_min'] ?? null;
        if ($userMin !== null && $listing->salary_max !== null && $listing->salary_max < $userMin) {
            return FilterReason::BelowSalaryMin;
        }

        return null;
    }
}
