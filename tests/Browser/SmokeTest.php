<?php

use App\Models\User;

it('renders the login page without JS errors', function () {
    visit('/login')
        ->assertSee('Sign in')
        ->assertNoJavascriptErrors();
});

it('lets a user log in and lands on home with the profile-completion checklist', function () {
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

it('renders the home page with the request-a-board section for a logged-in user', function () {
    User::factory()->ic()->create([
        'email' => 'ic@example.com',
        'password' => bcrypt('password'),
    ]);

    visit('/login')
        ->fill('input[type="email"]', 'ic@example.com')
        ->fill('input[type="password"]', 'password')
        ->click('button[type="submit"]')
        ->assertSee('Home')
        ->assertSee('Request a job board')
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
        ->assertSee('Home');

    visit('/admin-users')
        ->assertSee('Invite User')
        ->assertNoJavascriptErrors();
});
