<?php

use App\Models\Application;
use App\Models\User;

it('renders the resume content for the owning user', function () {
    $user = login(User::factory()->create(['name' => 'Casey Owner', 'email' => 'casey@example.test']));

    $application = Application::factory()
        ->ready()
        ->create(['user_id' => $user->id]);

    $response = $this->get(route('applications.print.resume', $application));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('Casey Owner')
        ->toContain($application->resume_content['summary'])
        ->toContain('window.print()');
});

it('renders each skill as its own chip', function () {
    $user = login();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'resume_content' => ['summary' => 'A summary.', 'skills' => ['PHP', 'Laravel']],
    ]);

    expect($this->get(route('applications.print.resume', $application))->getContent())
        ->toContain('<li>PHP</li>')
        ->toContain('<li>Laravel</li>');
});

it('uses borderless pages and keeps sections from splitting awkwardly', function () {
    $user = login();
    $application = Application::factory()->ready()->create(['user_id' => $user->id]);

    expect($this->get(route('applications.print.resume', $application))->getContent())
        ->toContain('@page { size: letter; margin: 0; }')
        ->toContain('page-break-inside: avoid')
        ->toContain('page-break-after: avoid');
});

it('returns 403 for a user who does not own the application', function () {
    $owner = User::factory()->create();
    login(User::factory()->create());

    $application = Application::factory()
        ->ready()
        ->create(['user_id' => $owner->id]);

    $this->get(route('applications.print.resume', $application))->assertForbidden();
});

it('shows an empty-state notice when the application has no resume content', function () {
    $user = login();
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'resume_content' => null,
    ]);

    $response = $this->get(route('applications.print.resume', $application));

    $response->assertOk();
    expect($response->getContent())
        ->toContain('No resume content yet')
        ->not->toContain('window.print()');
});
