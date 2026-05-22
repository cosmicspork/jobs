<?php

use App\Models\Application;
use App\Models\Listing;
use App\Models\User;

it('renders the cover letter for the owning user', function () {
    $user = login(User::factory()->create(['name' => 'Casey Owner']));
    $listing = Listing::factory()->create(['company' => 'Acme Corp']);

    $application = Application::factory()
        ->ready()
        ->create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);

    $response = $this->get(route('applications.print.cover-letter', $application));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('Casey Owner')
        ->toContain('Acme Corp')
        ->toContain('Dear Hiring Team at Acme Corp')
        ->toContain('Position: '.$application->cover_letter_content['subject_line'])
        ->toContain('window.print()');
});

it('returns 403 for a non-owner', function () {
    $owner = User::factory()->create();
    login(User::factory()->create());

    $application = Application::factory()->ready()->create(['user_id' => $owner->id]);

    $this->get(route('applications.print.cover-letter', $application))->assertForbidden();
});

it('shows an empty-state notice when no cover letter exists', function () {
    $user = login();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'cover_letter_content' => null,
    ]);

    $response = $this->get(route('applications.print.cover-letter', $application));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('No cover letter yet')
        ->not->toContain('window.print()');
});
