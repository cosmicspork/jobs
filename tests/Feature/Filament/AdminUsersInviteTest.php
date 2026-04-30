<?php

use App\Filament\Pages\AdminUsers;
use App\Mail\WelcomeUser;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
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
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
        expect($notification->url)->toContain('password-reset/reset');

        return true;
    });
});

it('lets admin send a password reset for an existing user', function () {
    Notification::fake();

    $target = User::factory()->create();

    Livewire::test(AdminUsers::class)
        ->callTableAction('sendPasswordReset', $target)
        ->assertNotified();

    Notification::assertSentTo($target, ResetPassword::class, function (ResetPassword $notification) {
        expect($notification->url)->toContain('password-reset/reset');

        return true;
    });
});

it('lets admin edit a users core fields and target criteria', function () {
    $editee = User::factory()->ic()->create(['email' => 'old@example.com']);
    $existingTarget = $editee->targetProfiles()->first();

    Livewire::test(AdminUsers::class)
        ->callTableAction('edit', $editee, data: [
            'name' => 'Updated Name',
            'email' => 'new@example.com',
            'is_admin' => true,
            'title' => $editee->title,
            'summary' => $editee->summary ?? '',
            'skills' => $editee->skills ?? [],
            'experience' => $editee->experience ?? [],
            'education' => $editee->education ?? [],
            'targets' => [
                [
                    'id' => $existingTarget->id,
                    'name' => 'Updated Target',
                    'positioning' => 'Updated positioning blurb.',
                    'target_titles' => ['Senior Engineer'],
                    'is_active' => true,
                    'sort_order' => 0,
                    'remote' => true,
                    'salary_min' => 200000,
                    'locations' => ['Remote'],
                    'must_have_keywords' => [],
                    'avoid_keywords' => [],
                ],
            ],
            'boards' => [],
            'digest_enabled' => true,
            'digest_time' => '08:00',
            'timezone' => 'America/Chicago',
        ])
        ->assertNotified();

    $editee->refresh();
    $existingTarget->refresh();

    expect($editee->name)->toBe('Updated Name')
        ->and($editee->email)->toBe('new@example.com')
        ->and($editee->is_admin)->toBeTrue()
        ->and($existingTarget->name)->toBe('Updated Target')
        ->and($existingTarget->criteria['salary_min'])->toBe(200000);
});
