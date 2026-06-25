<?php

use App\FilterReason;
use App\Models\Listing;
use App\Models\TargetProfile;
use App\Services\ListingFilter;

function filterTarget(array $criteria = []): TargetProfile
{
    return TargetProfile::factory()->make([
        'criteria' => array_merge([
            'remote' => false,
            'salary_min' => null,
            'locations' => ['Remote'],
            'must_have_keywords' => [],
            'avoid_keywords' => [],
        ], $criteria),
    ]);
}

function filterListing(array $attrs = []): Listing
{
    return Listing::factory()->make(array_merge([
        'title' => 'Senior Engineer',
        'company' => 'Acme',
        'description' => 'A generic role.',
        'remote' => true,
        'salary_max' => null,
        'raw_data' => [],
    ], $attrs));
}

beforeEach(function () {
    $this->filter = new ListingFilter;
});

it('blocks a non-remote listing when the target requires remote', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['remote' => false]),
        filterTarget(['remote' => true]),
    );

    expect($reason)->toBe(FilterReason::NotRemote);
});

it('blocks a listing whose salary maxes out below the target minimum', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['remote' => true, 'salary_max' => 100000]),
        filterTarget(['salary_min' => 175000]),
    );

    expect($reason)->toBe(FilterReason::BelowSalaryMin);
});

it('blocks an onsite listing outside the allowed onsite locations', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['remote' => false, 'description' => 'On-site in San Francisco.']),
        filterTarget(['locations' => ['Remote', 'Mumbai']]),
    );

    expect($reason)->toBe(FilterReason::LocationBlocked);
});

it('allows an onsite listing in an allowed onsite location', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['remote' => false, 'description' => 'Hybrid, 3 days in our Mumbai office.']),
        filterTarget(['locations' => ['Remote', 'Mumbai']]),
    );

    expect($reason)->toBeNull();
});

it('does not location-block a remote listing even with onsite-only locations', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['remote' => true, 'description' => 'Fully remote.']),
        filterTarget(['locations' => ['Mumbai']]),
    );

    expect($reason)->toBeNull();
});

it('blocks a listing that mentions none of the must-have keywords', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['description' => 'We build with Node.js and React.']),
        filterTarget(['must_have_keywords' => ['Laravel', 'PHP']]),
    );

    expect($reason)->toBe(FilterReason::MissingMustHave);
});

it('allows a must-have keyword found in raw_data tags', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['description' => 'Backend role.', 'raw_data' => ['tags' => ['php', 'laravel']]]),
        filterTarget(['must_have_keywords' => ['Laravel']]),
    );

    expect($reason)->toBeNull();
});

it('blocks an avoid keyword that appears in the title', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['title' => 'Contract PHP Developer']),
        filterTarget(['must_have_keywords' => ['PHP'], 'avoid_keywords' => ['contract']]),
    );

    expect($reason)->toBe(FilterReason::HitAvoidKeyword);
});

it('does not block an avoid keyword that only appears in the body', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing([
            'title' => 'Senior Laravel Engineer',
            'description' => 'You will mentor junior engineers on the team.',
        ]),
        filterTarget(['must_have_keywords' => ['Laravel'], 'avoid_keywords' => ['junior']]),
    );

    expect($reason)->toBeNull();
});

it('passes a listing with empty keyword criteria (no false positives)', function () {
    $reason = $this->filter->reasonToSkip(
        filterListing(['remote' => true]),
        filterTarget(),
    );

    expect($reason)->toBeNull();
});
