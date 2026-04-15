<?php

use App\Filament\Pages\AdminUsers;
use App\Mail\WelcomeUser;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    login(User::factory()->create(['is_admin' => true]));
});

it('creates a user, sends welcome + reset emails, and never exposes the password', function () {
    Mail::fake();
    Notification::fake();

    Livewire::test(AdminUsers::class)
        ->callAction('createUser', data: [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'is_admin' => false,
        ])
        ->assertNotified();

    $user = User::query()->where('email', 'new@example.com')->firstOrFail();

    expect($user->prompts)->toBeNull()
        ->and($user->is_admin)->toBeFalse();

    Mail::assertSent(WelcomeUser::class, fn ($mail) => $mail->hasTo('new@example.com'));
    Notification::assertSentTo($user, ResetPassword::class);
});

it('lets admin send a password reset for an existing user', function () {
    Notification::fake();

    $target = User::factory()->create();

    Livewire::test(AdminUsers::class)
        ->callTableAction('sendPasswordReset', $target)
        ->assertNotified();

    Notification::assertSentTo($target, ResetPassword::class);
});

it('lets admin edit a users core fields', function () {
    $target = User::factory()->ic()->create(['email' => 'old@example.com']);

    Livewire::test(AdminUsers::class)
        ->callTableAction('edit', $target, data: [
            'name' => 'Updated Name',
            'email' => 'new@example.com',
            'is_admin' => true,
            'title' => $target->title,
            'summary' => $target->summary ?? '',
            'skills' => $target->skills ?? [],
            'role_type' => $target->preferences['role_type'] ?? 'both',
            'experience' => $target->experience ?? [],
            'education' => $target->education ?? [],
            'remote' => true,
            'salary_min' => 200000,
            'locations' => ['Remote'],
            'boards' => [],
            'digest_enabled' => true,
            'digest_time' => '08:00',
            'timezone' => 'America/Chicago',
        ])
        ->assertNotified();

    $target->refresh();
    expect($target->name)->toBe('Updated Name')
        ->and($target->email)->toBe('new@example.com')
        ->and($target->is_admin)->toBeTrue()
        ->and($target->preferences['salary_min'])->toBe(200000);
});
