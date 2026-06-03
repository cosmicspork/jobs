<?php

use App\Ai\Agents\ResumeTailorAgent;
use App\ApplicationStatus;
use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Jobs\GenerateCoverLetter;
use App\Jobs\GenerateResume;
use App\Jobs\MarkApplicationFailed;
use App\Jobs\MarkApplicationReady;
use App\Models\Application;
use App\Models\Listing;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;
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

it('rejects a professional summary longer than the one-page cap', function () {
    $user = login();
    $target = targetFor($user);
    $listing = Listing::factory()->create();

    $application = Application::factory()->ready()->create([
        'user_id' => $user->id,
        'listing_id' => $listing->id,
        'target_profile_id' => $target->id,
    ]);

    Livewire::test(EditApplication::class, ['record' => $application->getRouteKey()])
        ->fillForm(['resume_content.summary' => str_repeat('a', 501)])
        ->call('save')
        ->assertHasFormErrors(['resume_content.summary' => 'max']);
});

it('regenerates the resume through a batch that settles the status', function () {
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
        ->callAction(TestAction::make('regenerateResume'), data: [
            'extra_instructions' => 'Lead with queue-layer work.',
        ])
        ->assertNotified();

    $application->refresh();

    expect($application->extra_instructions)->toBe('Lead with queue-layer work.')
        ->and($application->status)->toBe(ApplicationStatus::Generating);

    Bus::assertBatched(function ($batch) use ($application) {
        $then = $batch->thenCallbacks()[0] ?? null;
        $catch = $batch->catchCallbacks()[0] ?? null;

        return $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof GenerateResume
            && $then instanceof MarkApplicationReady
            && $then->application->is($application)
            && $catch instanceof MarkApplicationFailed;
    });
});

it('regenerates the cover letter through a batch that settles the status', function () {
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

    Bus::assertBatched(function ($batch) use ($application) {
        $then = $batch->thenCallbacks()[0] ?? null;
        $catch = $batch->catchCallbacks()[0] ?? null;

        return $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof GenerateCoverLetter
            && $then instanceof MarkApplicationReady
            && $then->application->is($application)
            && $catch instanceof MarkApplicationFailed;
    });
});

it('flips a regenerating application back to ready once the section finishes', function () {
    ResumeTailorAgent::fake([
        [
            'summary' => 'Tailored summary for the role.',
            'skills' => ['PHP / Laravel'],
            'experience' => [],
            'education' => [],
            'keyword_matches' => [],
        ],
    ]);

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
            'extra_instructions' => null,
        ])
        ->assertNotified();

    expect($application->fresh()->status)->toBe(ApplicationStatus::Ready);
});
