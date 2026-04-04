<?php

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\Application;
use App\Models\Listing;
use App\Relevance;
use Illuminate\Support\Collection;

function buildDigest(
    ?Collection $relevant = null,
    ?Collection $maybe = null,
    ?Collection $ready = null,
    ?Collection $failed = null,
    ?Collection $shortlisted = null,
    array $stats = [],
): DailyDigest {
    return new DailyDigest(
        relevantListings: $relevant ?? collect(),
        maybeListings: $maybe ?? collect(),
        readyApplications: $ready ?? collect(),
        failedApplications: $failed ?? collect(),
        shortlistedWithoutApplications: $shortlisted ?? collect(),
        stats: array_merge([
            'total_scraped' => 0,
            'relevant_count' => 0,
            'maybe_count' => 0,
            'irrelevant_count' => 0,
            'ai_total_cost' => 0.0,
            'ai_usage_breakdown' => [],
        ], $stats),
    );
}

it('has the correct subject line', function () {
    $mailable = buildDigest();

    $mailable->assertHasSubject('Daily Job Digest — '.now()->format('M j, Y'));
});

it('renders relevant listings with title company and skills', function () {
    $listing = Listing::factory()->scored(Relevance::Relevant)->create([
        'title' => 'Senior Laravel Developer',
        'company' => 'Acme Corp',
    ]);

    $mailable = buildDigest(
        relevant: collect([$listing]),
        stats: ['relevant_count' => 1],
    );

    $html = $mailable->render();

    expect($html)
        ->toContain('Senior Laravel Developer')
        ->toContain('Acme Corp')
        ->toContain('PHP')
        ->toContain('Laravel')
        ->toContain('View in Admin')
        ->toContain(route('filament.admin.resources.listings.view', $listing));
});

it('renders gaps as badges for relevant listings', function () {
    $listing = Listing::factory()->scored(Relevance::Relevant)->create();

    $mailable = buildDigest(relevant: collect([$listing]));

    $html = $mailable->render();

    expect($html)->toContain('Go');
});

it('renders maybe listings with count', function () {
    $listings = Listing::factory()->scored(Relevance::Maybe)->count(3)->create();

    $mailable = buildDigest(
        maybe: $listings,
        stats: ['maybe_count' => 3],
    );

    $html = $mailable->render();

    expect($html)->toContain('Listings (3)');
    foreach ($listings as $listing) {
        expect($html)->toContain($listing->title);
    }
});

it('renders ready application updates', function () {
    $application = Application::factory()->ready()->create();
    $application->load('listing');

    $mailable = buildDigest(ready: collect([$application]));

    $html = $mailable->render();

    expect($html)
        ->toContain('Ready')
        ->toContain($application->listing->title);
});

it('renders failed application updates', function () {
    $application = Application::factory()->state(['status' => ApplicationStatus::Failed])->create();
    $application->load('listing');

    $mailable = buildDigest(failed: collect([$application]));

    $html = $mailable->render();

    expect($html)
        ->toContain('Failed')
        ->toContain($application->listing->title);
});

it('renders shortlisted listings without applications', function () {
    $listing = Listing::factory()->scored()->shortlisted()->create([
        'title' => 'Staff Engineer at Startup',
    ]);

    $mailable = buildDigest(shortlisted: collect([$listing]));

    $html = $mailable->render();

    expect($html)
        ->toContain('Staff Engineer at Startup')
        ->toContain('Needs Application');
});

it('renders daily stats', function () {
    $mailable = buildDigest(stats: [
        'total_scraped' => 42,
        'relevant_count' => 5,
        'maybe_count' => 12,
        'irrelevant_count' => 25,
        'ai_total_cost' => 1.2345,
        'ai_usage_breakdown' => [
            ['model' => 'claude-haiku-4-5', 'cost' => 1.2345, 'requests' => 42],
        ],
    ]);

    $html = $mailable->render();

    expect($html)
        ->toContain('42')
        ->toContain('$1.23')
        ->toContain('claude-haiku-4-5')
        ->toContain('42 requests');
});

it('renders empty state messages when no data', function () {
    $mailable = buildDigest();

    $html = $mailable->render();

    expect($html)
        ->toContain('No new relevant listings today.')
        ->toContain('No new maybe listings today.')
        ->toContain('No application updates.');
});
