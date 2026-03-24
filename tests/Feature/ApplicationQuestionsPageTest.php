<?php

use App\ApplicationQuestionSetStatus;
use App\Filament\Pages\ApplicationQuestions;
use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the page', function () {
    $this->get(route('filament.admin.pages.application-questions'))
        ->assertSuccessful();
});

it('renders with a listing query param', function () {
    $listing = Listing::factory()->create();

    $this->get(route('filament.admin.pages.application-questions', ['listing' => $listing->id]))
        ->assertSuccessful();
});

it('loads an existing question set for a listing', function () {
    $listing = Listing::factory()->create();
    $set = ApplicationQuestionSet::factory()->reviewed()->create(['listing_id' => $listing->id]);
    ApplicationQuestion::factory()->reviewed()->create([
        'question_set_id' => $set->id,
        'question' => 'Why do you want this role?',
        'answer' => 'I love building teams.',
    ]);

    Livewire::withQueryParams(['listing' => $listing->id])
        ->test(ApplicationQuestions::class)
        ->assertSet('showResults', true)
        ->assertSet('questionSetId', $set->id);
});

it('shows the input form when no existing question set', function () {
    Livewire::test(ApplicationQuestions::class)
        ->assertSet('showResults', false)
        ->assertSet('questionSetId', null);
});

it('can reset the form', function () {
    $set = ApplicationQuestionSet::factory()->reviewed()->create();
    ApplicationQuestion::factory()->reviewed()->create(['question_set_id' => $set->id]);

    Livewire::withQueryParams(['listing' => $set->listing_id])
        ->test(ApplicationQuestions::class)
        ->assertSet('showResults', true)
        ->call('resetForm')
        ->assertSet('showResults', false)
        ->assertSet('questionSetId', null);
});

it('can save final answers', function () {
    $set = ApplicationQuestionSet::factory()->reviewed()->create();
    $question = ApplicationQuestion::factory()->reviewed()->create([
        'question_set_id' => $set->id,
        'suggested_answer' => 'Original suggestion',
    ]);

    $component = Livewire::withQueryParams(['listing' => $set->listing_id])
        ->test(ApplicationQuestions::class);

    $component->set('results.0.suggested_answer', 'My edited final answer')
        ->call('saveFinalAnswers')
        ->assertNotified();

    expect($question->fresh()->final_answer)->toBe('My edited final answer')
        ->and($set->fresh()->status)->toBe(ApplicationQuestionSetStatus::Finalized);
});
