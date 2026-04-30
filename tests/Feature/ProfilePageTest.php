<?php

use App\Models\User;

it('disables the digest toggle for users with incomplete profiles', function () {
    login(User::factory()->create());

    $this->get(route('filament.admin.pages.profile'))
        ->assertSee('Finish your profile (summary, skills, and at least one active target with positioning, target titles, and a remote preference) before enabling');
});

it('does not show the digest gate hint for users with complete profiles', function () {
    login(User::factory()->ic()->create());

    $this->get(route('filament.admin.pages.profile'))
        ->assertDontSee('Finish your profile (summary, skills, and at least one active target with positioning, target titles, and a remote preference) before enabling');
});
