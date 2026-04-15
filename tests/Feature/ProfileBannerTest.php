<?php

use App\Models\User;

it('shows the profile-incomplete banner to non-admin users with bare profiles', function () {
    login(User::factory()->create());

    $this->get(route('filament.admin.resources.listings.index'))
        ->assertSee('Finish setting up your profile');
});

it('hides the banner for admin users', function () {
    login(User::factory()->create(['is_admin' => true]));

    $this->get(route('filament.admin.resources.listings.index'))
        ->assertDontSee('Finish setting up your profile');
});

it('hides the banner once the user has filled the minimum profile', function () {
    login(User::factory()->ic()->create());

    $this->get(route('filament.admin.resources.listings.index'))
        ->assertDontSee('Finish setting up your profile');
});

it('hides the banner on the profile page itself to avoid noise', function () {
    login(User::factory()->create());

    $this->get(route('filament.admin.pages.profile'))
        ->assertDontSee('Finish setting up your profile');
});
