<?php

use App\Models\Listing;
use App\Models\User;

it('renders the cover letter template with letterhead', function () {
    $user = login(User::factory()->create([
        'name' => 'Josh Bowen',
        'email' => 'josh@example.com',
    ]));

    $listing = Listing::factory()->create(['company' => 'Acme Corp']);

    $html = view('cover-letter.base', [
        'profile' => array_merge($user->getProfileData(), ['email' => 'josh@example.com']),
        'subjectLine' => 'Engineering Manager',
        'body' => "First paragraph.\n\nSecond paragraph.",
        'listing' => $listing,
    ])->render();

    expect($html)
        ->toContain('Josh Bowen')
        ->toContain('josh@example.com')
        ->toContain('Acme Corp')
        ->toContain('Position: Engineering Manager')
        ->toContain('Dear Hiring Team at Acme Corp')
        ->toContain('First paragraph.')
        ->toContain('Second paragraph.')
        ->toContain('Sincerely,');
});

it('renders without email when not set', function () {
    $user = login(User::factory()->create([
        'name' => 'Josh Bowen',
        'email' => 'josh@example.com',
    ]));

    $listing = Listing::factory()->create(['company' => 'Test Co']);

    $html = view('cover-letter.base', [
        'profile' => array_merge($user->getProfileData(), ['email' => '']),
        'subjectLine' => 'Developer Role',
        'body' => 'Body text here.',
        'listing' => $listing,
    ])->render();

    expect($html)
        ->toContain('Position: Developer Role')
        ->toContain('Dear Hiring Team at Test Co');
});
