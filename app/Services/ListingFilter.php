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

        $haystack = $listing->searchableText();

        // LOCATION: a non-remote listing is viable only if one of the target's
        // allowed onsite locations (its locations minus "Remote") appears in
        // the text. A purely Remote locations list imposes no onsite gate here
        // — the NotRemote gate above already covers the remote-required case.
        $onsiteAllowed = array_values(array_filter(
            array_filter((array) ($criteria['locations'] ?? [])),
            fn ($location): bool => mb_strtolower(trim((string) $location)) !== 'remote',
        ));

        if ($listing->remote !== true && $onsiteAllowed !== []
            && ! $this->anyKeywordMatches($onsiteAllowed, $haystack)) {
            return FilterReason::LocationBlocked;
        }

        // MUST-HAVE: if set, the listing must mention at least one of them.
        $mustHave = array_filter((array) ($criteria['must_have_keywords'] ?? []));
        if ($mustHave !== [] && ! $this->anyKeywordMatches($mustHave, $haystack)) {
            return FilterReason::MissingMustHave;
        }

        // AVOID: only block on the title — an avoid term buried in the body
        // (e.g. "mentor junior engineers" in a senior role) is not a blocker.
        $avoid = array_filter((array) ($criteria['avoid_keywords'] ?? []));
        if ($avoid !== [] && $this->anyKeywordMatches($avoid, mb_strtolower((string) $listing->title))) {
            return FilterReason::HitAvoidKeyword;
        }

        return null;
    }

    /**
     * Case-insensitive substring match: true if any needle appears in the
     * already-lower-cased haystack.
     *
     * @param  array<int, string>  $needles
     */
    private function anyKeywordMatches(array $needles, string $haystack): bool
    {
        foreach ($needles as $needle) {
            $n = mb_strtolower(trim((string) $needle));

            if ($n !== '' && str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}
