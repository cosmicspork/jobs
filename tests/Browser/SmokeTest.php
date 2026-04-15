<?php

use App\Models\User;

it('renders the login page without JS errors', function () {
    visit('/login')
        ->assertSee('Sign in')
        ->assertNoJavascriptErrors();
});

it('lets a user log in and lands on the dashboard with the profile-incomplete banner', function () {
    User::factory()->create([
        'email' => 'smoke@example.com',
        'password' => bcrypt('password'),
    ]);

    visit('/login')
        ->fill('input[type="email"]', 'smoke@example.com')
        ->fill('input[type="password"]', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Finish setting up your profile')
        ->assertNoJavascriptErrors();
});

it('renders the request-a-board page for a logged-in user with no JS errors', function () {
    User::factory()->ic()->create([
        'email' => 'ic@example.com',
        'password' => bcrypt('password'),
    ]);

    visit('/login')
        ->fill('input[type="email"]', 'ic@example.com')
        ->fill('input[type="password"]', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Dashboard');

    visit('/request-board')
        ->assertSee('Request a Job Board')
        ->assertSee('Send Request')
        ->assertNoJavascriptErrors();
});

it('renders the admin users page with invite action for an admin', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);

    visit('/login')
        ->fill('input[type="email"]', 'admin@example.com')
        ->fill('input[type="password"]', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Dashboard');

    visit('/admin-users')
        ->assertSee('Invite User')
        ->assertNoJavascriptErrors();
});
