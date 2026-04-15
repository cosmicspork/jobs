<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

it('disables digests when the signed unsubscribe link is hit', function () {
    $user = User::factory()->create(['digest_enabled' => true]);

    $url = URL::signedRoute('digest.unsubscribe', ['user' => $user->id]);

    $this->get($url)->assertSuccessful()->assertSee('paused');

    expect($user->refresh()->digest_enabled)->toBeFalse();
});

it('rejects an unsigned unsubscribe request', function () {
    $user = User::factory()->create(['digest_enabled' => true]);

    $this->get(route('digest.unsubscribe', ['user' => $user->id]))
        ->assertForbidden();

    expect($user->refresh()->digest_enabled)->toBeTrue();
});
