<?php

use App\Mail\WelcomeUser;
use App\Models\User;

it('renders the welcome email with onboarding guidance and forgot-password link', function () {
    $user = User::factory()->create(['name' => 'Jane', 'email' => 'jane@example.com']);

    $rendered = (new WelcomeUser($user))->render();

    expect($rendered)
        ->toContain('Welcome, Jane')
        ->toContain('Set your password')
        ->toContain('Fill out your profile')
        ->toContain('Subscribe to job boards')
        ->toContain(route('filament.admin.auth.password-reset.request'));
});
