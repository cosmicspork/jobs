<?php

use App\Ai\Agents\ApplicationQuestionsAgent;
use App\Ai\ProviderFreeze;
use App\ApplicationQuestionSetStatus;
use App\Filament\Pages\ApplicationQuestions;
use App\Models\ApplicationQuestion;
use App\Models\ApplicationQuestionSet;
use App\Models\Listing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Exceptions\AiException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = login();
});

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
    $set = ApplicationQuestionSet::factory()->reviewed()->create([
        'listing_id' => $listing->id,
        'user_id' => $this->user->id,
    ]);
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
    $set = ApplicationQuestionSet::factory()->reviewed()->create([
        'user_id' => $this->user->id,
    ]);
    ApplicationQuestion::factory()->reviewed()->create(['question_set_id' => $set->id]);

    Livewire::withQueryParams(['listing' => $set->listing_id])
        ->test(ApplicationQuestions::class)
        ->assertSet('showResults', true)
        ->call('resetForm')
        ->assertSet('showResults', false)
        ->assertSet('questionSetId', null);
});

it('can save final answers', function () {
    $set = ApplicationQuestionSet::factory()->reviewed()->create([
        'user_id' => $this->user->id,
    ]);
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

it('short-circuits review with a notification when the provider is frozen on entry', function () {
    $this->user = login(User::factory()->ic()->create());
    $target = $this->user->targetProfiles()->first();
    ApplicationQuestionsAgent::fake()->preventStrayPrompts();

    ProviderFreeze::freezeProvider(
        config('ai.agents.app_questions.provider'),
        CarbonImmutable::now()->addHours(2),
    );

    Livewire::test(ApplicationQuestions::class)
        ->set('data.target_profile_id', $target->id)
        ->set('data.questions', [['question' => 'Why this role?', 'answer' => 'Because it fits.']])
        ->call('submitForReview')
        ->assertNotified();

    ApplicationQuestionsAgent::assertNeverPrompted();

    $questionSet = ApplicationQuestionSet::first();
    expect($questionSet->status)->toBe(ApplicationQuestionSetStatus::Draft);
});

it('surfaces a provider-frozen notification when the agent throws a usage-limit error', function () {
    $this->user = login(User::factory()->ic()->create());
    $target = $this->user->targetProfiles()->first();

    ApplicationQuestionsAgent::fake(function () {
        throw new AiException(
            'Anthropic Error [400]: invalid_request_error - You have reached your specified API usage limits. You will regain access on 2026-06-01 at 00:00 UTC.',
            400,
        );
    });

    Livewire::test(ApplicationQuestions::class)
        ->set('data.target_profile_id', $target->id)
        ->set('data.questions', [['question' => 'Why this role?', 'answer' => 'Because it fits.']])
        ->call('submitForReview')
        ->assertNotified();

    $questionSet = ApplicationQuestionSet::first();
    expect($questionSet->status)->toBe(ApplicationQuestionSetStatus::Draft);

    $frozenUntil = ProviderFreeze::providerFrozenUntil(config('ai.agents.app_questions.provider'));
    expect($frozenUntil)->not->toBeNull()
        ->and($frozenUntil->toDateString())->toBe('2026-06-01');
});
