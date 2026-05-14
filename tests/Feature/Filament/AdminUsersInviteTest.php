<?php

use App\Filament\Pages\AdminUsers;
use App\Mail\WelcomeUser;
use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use App\Relevance;
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
            'monthly_ai_cap_usd' => '12.50',
        ])
        ->assertNotified();

    $editee->refresh();
    $existingTarget->refresh();

    expect($editee->name)->toBe('Updated Name')
        ->and($editee->email)->toBe('new@example.com')
        ->and($editee->is_admin)->toBeTrue()
        ->and((float) $editee->monthly_ai_cap_usd)->toBe(12.50)
        ->and($existingTarget->name)->toBe('Updated Target')
        ->and($existingTarget->criteria['salary_min'])->toBe(200000);
});

it('clears the per-user AI cap when the field is left blank', function () {
    $editee = User::factory()->ic()->create([
        'email' => 'capped@example.com',
        'monthly_ai_cap_usd' => 20.00,
    ]);
    $existingTarget = $editee->targetProfiles()->first();

    Livewire::test(AdminUsers::class)
        ->callTableAction('edit', $editee, data: [
            'name' => $editee->name,
            'email' => $editee->email,
            'is_admin' => $editee->is_admin,
            'summary' => $editee->summary ?? '',
            'skills' => $editee->skills ?? [],
            'experience' => $editee->experience ?? [],
            'education' => $editee->education ?? [],
            'targets' => [
                [
                    'id' => $existingTarget->id,
                    'name' => $existingTarget->name,
                    'positioning' => $existingTarget->positioning,
                    'target_titles' => $existingTarget->target_titles,
                    'is_active' => $existingTarget->is_active,
                    'sort_order' => $existingTarget->sort_order,
                    'remote' => $existingTarget->criteria['remote'] ?? true,
                    'salary_min' => $existingTarget->criteria['salary_min'] ?? null,
                    'locations' => $existingTarget->criteria['locations'] ?? [],
                    'must_have_keywords' => $existingTarget->criteria['must_have_keywords'] ?? [],
                    'avoid_keywords' => $existingTarget->criteria['avoid_keywords'] ?? [],
                ],
            ],
            'boards' => [],
            'digest_enabled' => $editee->digest_enabled,
            'digest_time' => $editee->digest_time,
            'timezone' => $editee->timezone,
            'monthly_ai_cap_usd' => '',
        ])
        ->assertNotified();

    expect($editee->refresh()->monthly_ai_cap_usd)->toBeNull();
});

it('admin edit preserves existing target id and listing_user pivots', function () {
    $editee = User::factory()->ic()->create();
    $existingTarget = $editee->targetProfiles()->first();
    $listing = Listing::factory()->create();
    $pivot = ListingUser::create([
        'listing_id' => $listing->id,
        'user_id' => $editee->id,
        'target_profile_id' => $existingTarget->id,
        'relevance' => Relevance::Relevant,
        'scored_at' => now(),
        'starred_at' => now(),
    ]);

    Livewire::test(AdminUsers::class)
        ->callTableAction('edit', $editee, data: [
            'name' => $editee->name,
            'email' => $editee->email,
            'is_admin' => $editee->is_admin,
            'summary' => $editee->summary ?? '',
            'skills' => $editee->skills ?? [],
            'experience' => $editee->experience ?? [],
            'education' => $editee->education ?? [],
            'targets' => [
                [
                    'id' => $existingTarget->id,
                    'name' => 'Renamed by admin',
                    'positioning' => $existingTarget->positioning,
                    'target_titles' => $existingTarget->target_titles,
                    'is_active' => true,
                    'sort_order' => $existingTarget->sort_order,
                    'remote' => $existingTarget->criteria['remote'] ?? true,
                    'salary_min' => $existingTarget->criteria['salary_min'] ?? null,
                    'locations' => $existingTarget->criteria['locations'] ?? [],
                    'must_have_keywords' => $existingTarget->criteria['must_have_keywords'] ?? [],
                    'avoid_keywords' => $existingTarget->criteria['avoid_keywords'] ?? [],
                ],
            ],
            'boards' => [],
            'digest_enabled' => $editee->digest_enabled,
            'digest_time' => $editee->digest_time,
            'timezone' => $editee->timezone,
            'monthly_ai_cap_usd' => '',
        ])
        ->assertNotified();

    $existingTarget->refresh();
    expect($existingTarget->name)->toBe('Renamed by admin')
        ->and($editee->targetProfiles()->where('is_active', true)->count())->toBe(1)
        ->and(ListingUser::find($pivot->id))->not->toBeNull()
        ->and(ListingUser::find($pivot->id)->starred_at)->not->toBeNull();
});
