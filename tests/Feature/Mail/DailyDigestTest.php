<?php

use App\ApplicationStatus;
use App\Mail\DailyDigest;
use App\Models\Application;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
use Illuminate\Support\Collection;

function createScoredListing(User $user, Relevance $relevance = Relevance::Relevant, array $listingAttrs = [], ?array $scoreDataOverride = null): Listing
{
    $target = $user->targetProfiles()->first() ?? targetFor($user);
    $listing = Listing::factory()->create($listingAttrs);
    $scoreData = $scoreDataOverride ?? [
        'matched_skills' => ['PHP', 'Laravel'],
        'gaps' => ['Go'],
        'reasoning' => 'Good match for a Laravel developer.',
        'posting_quality_signals' => ['salary listed'],
    ];

    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $user->id,
        'target_profile_id' => $target->id,
        'relevance' => $relevance,
        'score_data' => $scoreData,
        'scored_at' => now(),
    ]);

    // Attach score_data + target name to the listing object so the view template can access them.
    $listing->setAttribute('score_data', $scoreData);
    $listing->setAttribute('target_name', $target->name);

    return $listing;
}

function buildDigest(
    ?User $user = null,
    ?Collection $relevant = null,
    ?Collection $maybe = null,
    ?Collection $ready = null,
    ?Collection $failed = null,
    ?Collection $shortlisted = null,
    array $stats = [],
): DailyDigest {
    return new DailyDigest(
        user: $user ?? User::factory()->create(),
        relevantListings: $relevant ?? collect(),
        maybeListings: $maybe ?? collect(),
        readyApplications: $ready ?? collect(),
        failedApplications: $failed ?? collect(),
        shortlistedWithoutApplications: $shortlisted ?? collect(),
        stats: array_merge([
            'screened_24h' => 0,
            'screened_7d' => 0,
            'relevant_7d' => 0,
            'maybe_7d' => 0,
        ], $stats),
    );
}

beforeEach(function () {
    Mail::fake();
    $this->user = login();
});

it('has the correct subject line', function () {
    $mailable = buildDigest(user: $this->user);

    $mailable->assertHasSubject('Daily Job Digest — '.now()->format('M j, Y'));
});

it('renders relevant listings with title company and skills', function () {
    $listing = createScoredListing($this->user, Relevance::Relevant, [
        'title' => 'Senior Laravel Developer',
        'company' => 'Acme Corp',
    ]);

    $mailable = buildDigest(
        user: $this->user,
        relevant: collect([$listing]),
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
    $listing = createScoredListing($this->user, Relevance::Relevant);

    $mailable = buildDigest(user: $this->user, relevant: collect([$listing]));

    $html = $mailable->render();

    expect($html)->toContain('Go');
});

it('renders maybe listings with count', function () {
    $listings = collect();
    for ($i = 0; $i < 3; $i++) {
        $listings->push(createScoredListing($this->user, Relevance::Maybe));
    }

    $mailable = buildDigest(
        user: $this->user,
        maybe: $listings,
    );

    $html = $mailable->render();

    expect($html)->toContain('Listings (3)');
    foreach ($listings as $listing) {
        expect($html)->toContain($listing->title);
    }
});

it('shows the score reasoning on maybe listings', function () {
    $listing = createScoredListing(
        $this->user,
        Relevance::Maybe,
        scoreDataOverride: [
            'matched_skills' => ['PHP'],
            'gaps' => ['Kubernetes'],
            'reasoning' => 'Strong Laravel fit but role leans heavily on infra ops.',
            'posting_quality_signals' => [],
        ],
    );

    $mailable = buildDigest(user: $this->user, maybe: collect([$listing]));

    $html = $mailable->render();

    expect($html)->toContain('Strong Laravel fit but role leans heavily on infra ops.');
});

it('renders ready application updates', function () {
    $application = Application::factory()->ready()->create(['user_id' => $this->user->id]);
    $application->load('listing');

    $mailable = buildDigest(user: $this->user, ready: collect([$application]));

    $html = $mailable->render();

    expect($html)
        ->toContain('Ready')
        ->toContain($application->listing->title);
});

it('renders failed application updates', function () {
    $application = Application::factory()->state(['status' => ApplicationStatus::Failed])->create(['user_id' => $this->user->id]);
    $application->load('listing');

    $mailable = buildDigest(user: $this->user, failed: collect([$application]));

    $html = $mailable->render();

    expect($html)
        ->toContain('Failed')
        ->toContain($application->listing->title);
});

it('renders shortlisted listings without applications', function () {
    $target = targetFor($this->user);
    $listing = Listing::factory()->create(['title' => 'Staff Engineer at Startup']);
    ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
        'target_profile_id' => $target->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        'shortlisted_at' => now(),
    ]);

    $mailable = buildDigest(user: $this->user, shortlisted: collect([$listing]));

    $html = $mailable->render();

    expect($html)
        ->toContain('Staff Engineer at Startup')
        ->toContain('Needs Application');
});

it('renders the 7-day trend section', function () {
    $mailable = buildDigest(user: $this->user, stats: [
        'screened_24h' => 12,
        'screened_7d' => 84,
        'relevant_7d' => 6,
        'maybe_7d' => 14,
    ]);

    $html = $mailable->render();

    expect($html)
        ->toContain('Last 7 Days')
        ->toContain('84')
        ->toContain('listings screened')
        ->toContain('6')
        ->toContain('relevant')
        ->toContain('14')
        ->toContain('maybe')
        ->not->toContain('AI Cost')
        ->not->toContain('Last 24 Hours');
});

it('shows the screened count in the empty-state when listings were screened', function () {
    $mailable = buildDigest(user: $this->user, stats: [
        'screened_24h' => 47,
        'screened_7d' => 47,
    ]);

    $html = $mailable->render();

    expect($html)
        ->toContain('we screened 47 listings')
        ->not->toContain('No new relevant listings today.');
});

it('renders empty state messages when nothing was screened', function () {
    $mailable = buildDigest(user: $this->user);

    $html = $mailable->render();

    expect($html)
        ->toContain('No new relevant listings today.')
        ->toContain('No new maybe listings today.')
        ->toContain('No application updates.');
});
