<?php

namespace App\Services;

use App\FilterReason;
use App\Models\Listing;
use App\Models\TargetProfile;

class ListingFilter
{
    public function reasonToSkip(Listing $listing, TargetProfile $target): ?FilterReason
    {
        $criteria = $target->criteria ?? [];

        if (($criteria['remote'] ?? false) === true && $listing->remote === false) {
            return FilterReason::NotRemote;
        }

        $min = $criteria['salary_min'] ?? null;
        if ($min !== null && $listing->salary_max !== null && $listing->salary_max < $min) {
            return FilterReason::BelowSalaryMin;
        }

        return null;
    }
}
