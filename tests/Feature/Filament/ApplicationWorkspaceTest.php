<?php

use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

it('lists only the signed-in users own applications on the index', function () {
    $user = login();
    targetFor($user);
    $ownListing = Listing::factory()->create(['title' => 'Mine']);
    $otherUser = User::factory()->create();
    $otherListing = Listing::factory()->create(['title' => 'Theirs']);

    $own = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $ownListing->id,
        'target_profile_id' => $user->targetProfiles()->first()->id,
    ]);
    $foreign = Application::factory()->ready()->create([
        'user_id' => $otherUser->id,
        'listing_id' => $otherListing->id,
        'target_profile_id' => targetFor($otherUser)->id,
    ]);

    Livewire::test(ListApplications::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$foreign]);
});

it('hydrates the workspace form from resume + cover-letter JSON columns', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
        'target_profile_id' => $target->id,
        'notes' => 'Remember to follow up.',
    ]);

    Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
        ->assertFormSet([
            'resume_content.summary' => $application->resume_content['summary'],
            'cover_letter_content.subject_line' => $application->cover_letter_content['subject_line'],
            'cover_letter_content.body' => $application->cover_letter_content['body'],
            'notes' => 'Remember to follow up.',
        ]);
});

it('saves edits back to the JSON columns and the notes field', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
        'target_profile_id' => $target->id,
    ]);

    Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
        ->fillForm([
            'resume_content.summary' => 'Edited summary.',
            'cover_letter_content.body' => 'Edited body paragraph.',
            'notes' => 'A new note.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $application->refresh();

    expect($application->resume_content['summary'])->toBe('Edited summary.')
        ->and($application->cover_letter_content['body'])->toBe('Edited body paragraph.')
        ->and($application->notes)->toBe('A new note.');
});

it('dispatches GenerateResume with extra_instructions when the regenerate action fires', function () {
    Queue::fake();
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
        'target_profile_id' => $target->id,
    ]);

    Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
        ->callAction(TestAction::make('regenerateResume'), data: [
            'extra_instructions' => 'Lead with queue-layer work.',
        ])
        ->assertNotified();

    $application->refresh();

    expect($application->extra_instructions)->toBe('Lead with queue-layer work.');
    Queue::assertPushed(GenerateResume::class, fn ($job) => $job->application->is($application));
});

it('dispatches GenerateCoverLetter when the cover-letter regenerate action fires', function () {
    Bus::fake();
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
        'target_profile_id' => $target->id,
    ]);

    Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
        ->callAction(TestAction::make('regenerateCoverLetter'), data: [
            'extra_instructions' => null,
        ])
        ->assertNotified();

    Bus::assertDispatched(GenerateCoverLetter::class);
});
